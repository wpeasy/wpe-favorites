<?php
/**
 * FluentCart licensing API integration.
 *
 * Handles license activation, deactivation, and status checks
 * against the FluentCart API.
 *
 * @package WPE\Favorites
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Licensing;

defined('ABSPATH') || exit;

final class FluentLicensing {

    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $config = [];

    public string $settingsKey = '';

    /**
     * Whether to use network-wide (site_option) storage on multisite.
     * One license activation covers the entire network.
     *
     * @var bool
     */
    private bool $networkWide = false;

    /**
     * Register the licensing system.
     *
     * @param array<string, mixed> $config Licensing configuration.
     * @return self
     */
    public function register(array $config = []): self {
        if (self::$instance) {
            return self::$instance;
        }

        if (empty($config['basename']) || empty($config['version']) || empty($config['api_url'])) {
            throw new \Exception('Invalid configuration for FluentLicensing. Provide basename, version, and api_url.');
        }

        $this->config = $config;
        $baseName = $config['basename'] ?? plugin_basename(__FILE__);

        $slug = $config['slug'] ?? explode('/', $baseName)[0];
        $this->config['slug'] = (string) $slug;

        $this->settingsKey = $config['settings_key'] ?? '__' . $this->config['slug'] . '_sl_info';

        // On multisite, store license network-wide so one activation covers all sites.
        $this->networkWide = is_multisite();

        $config = $this->config;

        if (empty($config['license_key']) && empty($config['license_key_callback'])) {
            $config['license_key_callback'] = fn() => $this->getCurrentLicenseKey();
        }

        new PluginUpdater($config);

        self::$instance = $this;

        return self::$instance;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key Configuration key.
     * @return mixed
     */
    public function getConfig(string $key): mixed {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        throw new \Exception("Configuration key '{$key}' does not exist.");
    }

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function getInstance(): self {
        if (!self::$instance) {
            throw new \Exception('Licensing is not registered. Call register() first.');
        }

        return self::$instance;
    }

    /**
     * Check if licensing has been registered.
     *
     * @return bool
     */
    public static function isRegistered(): bool {
        return self::$instance !== null;
    }

    /**
     * Activate a license key.
     *
     * @param string $licenseKey The license key to activate.
     * @return array<string, mixed>|\WP_Error
     */
    public function activate(string $licenseKey = ''): array|\WP_Error {
        if (!$licenseKey) {
            return new \WP_Error('license_key_missing', 'License key is required for activation.');
        }

        $response = $this->apiRequest('activate_license', [
            'license_key' => $licenseKey,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $saveData = [
            'license_key'     => $licenseKey,
            'status'          => $response['status'] ?? 'valid',
            'variation_id'    => $response['variation_id'] ?? '',
            'variation_title' => $response['variation_title'] ?? '',
            'expires'         => $response['expiration_date'] ?? '',
            'activation_hash' => $response['activation_hash'] ?? '',
        ];

        $this->updateOption($saveData);

        return $saveData;
    }

    /**
     * Deactivate the current license.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function deactivate(): array|\WP_Error {
        $deactivated = $this->apiRequest('deactivate_license', [
            'license_key' => $this->getCurrentLicenseKey(),
        ]);

        $this->deleteOption();

        return $deactivated;
    }

    /**
     * Get the current license status.
     *
     * @param bool $remoteFetch Whether to fetch from the remote API.
     * @return array<string, mixed>|\WP_Error
     */
    public function getStatus(bool $remoteFetch = false): array|\WP_Error {
        $currentLicense = $this->getOption([]);

        if (!$currentLicense || !is_array($currentLicense) || empty($currentLicense['license_key'])) {
            $currentLicense = [
                'license_key'     => '',
                'status'          => 'unregistered',
                'variation_id'    => '',
                'variation_title' => '',
                'expires'         => '',
            ];
        }

        if (!$remoteFetch) {
            return $currentLicense;
        }

        $remoteStatus = $this->apiRequest('check_license', [
            'license_key'     => $currentLicense['license_key'],
            'activation_hash' => $currentLicense['activation_hash'] ?? '',
            'item_id'         => $this->config['item_id'],
            'site_url'        => $this->getSiteUrl(),
        ]);

        if (is_wp_error($remoteStatus)) {
            return $remoteStatus;
        }

        $status    = $remoteStatus['status'] ?? 'unregistered';
        $errorType = $remoteStatus['error_type'] ?? '';

        if (!empty($currentLicense['status'])) {
            $currentLicense['status'] = $status;

            if (!empty($remoteStatus['expiration_date'])) {
                $currentLicense['expires'] = sanitize_text_field($remoteStatus['expiration_date']);
            }

            if (!empty($remoteStatus['variation_id'])) {
                $currentLicense['variation_id'] = sanitize_text_field($remoteStatus['variation_id']);
            }

            if (!empty($remoteStatus['variation_title'])) {
                $currentLicense['variation_title'] = sanitize_text_field($remoteStatus['variation_title']);
            }

            $this->updateOption($currentLicense);
        } else {
            $currentLicense['status'] = 'error';
        }

        $currentLicense['renew_url']  = $remoteStatus['renew_url'] ?? '';
        $currentLicense['is_expired'] = $remoteStatus['is_expired'] ?? false;

        if ($errorType) {
            $currentLicense['error_type']    = $errorType;
            $currentLicense['error_message'] = $remoteStatus['message'] ?? '';
        }

        return $currentLicense;
    }

    /**
     * Get the current license key.
     *
     * @return string
     */
    public function getCurrentLicenseKey(): string {
        $status = $this->getStatus();
        return $status['license_key'] ?? '';
    }

    /* ------------------------------------------------------------------ */
    /*  Network-aware storage helpers                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Get a license option (network-wide on multisite).
     *
     * @param mixed $default Default value.
     * @return mixed
     */
    private function getOption(mixed $default = []) {
        if ($this->networkWide) {
            return get_site_option($this->settingsKey, $default);
        }

        return get_option($this->settingsKey, $default);
    }

    /**
     * Update a license option (network-wide on multisite).
     *
     * @param mixed $value Option value.
     */
    private function updateOption(mixed $value): void {
        if ($this->networkWide) {
            update_site_option($this->settingsKey, $value);
        } else {
            update_option($this->settingsKey, $value, false);
        }
    }

    /**
     * Delete a license option (network-wide on multisite).
     */
    private function deleteOption(): void {
        if ($this->networkWide) {
            delete_site_option($this->settingsKey);
        } else {
            delete_option($this->settingsKey);
        }
    }

    /**
     * Get the site URL to send to the license API.
     *
     * On multisite, uses the network home URL so all sites share
     * one activation rather than consuming separate slots.
     *
     * @return string
     */
    private function getSiteUrl(): string {
        if ($this->networkWide) {
            return network_home_url();
        }

        return home_url();
    }

    /**
     * Send a request to the FluentCart API.
     *
     * @param string               $action API action.
     * @param array<string, mixed> $data   Request data.
     * @return array<string, mixed>|\WP_Error
     */
    private function apiRequest(string $action, array $data = []): array|\WP_Error {
        $fullUrl = add_query_arg(['fluent-cart' => $action], $this->config['api_url']);

        $defaults = [
            'item_id'         => $this->config['item_id'],
            'current_version' => $this->config['version'],
            'site_url'        => $this->getSiteUrl(),
        ];

        $payload = wp_parse_args($data, $defaults);

        $response = wp_remote_post($fullUrl, [
            'timeout'   => 15,
            'body'      => $payload,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 !== wp_remote_retrieve_response_code($response)) {
            $errorData = wp_remote_retrieve_body($response);
            $message   = 'API request failed with status code: ' . wp_remote_retrieve_response_code($response);

            if (!empty($errorData)) {
                $decodedData = json_decode($errorData, true);
                if ($decodedData) {
                    $errorData = $decodedData;
                }
                if (!empty($errorData['message'])) {
                    $message = (string) $errorData['message'];
                }
            }

            return new \WP_Error('api_error', $message, $errorData);
        }

        $responseData = json_decode(wp_remote_retrieve_body($response), true);

        if ($responseData) {
            return $responseData;
        }

        return new \WP_Error('api_error', 'API request returned an empty or invalid JSON response.');
    }
}
