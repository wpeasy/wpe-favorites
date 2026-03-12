<?php
/**
 * FluentCart plugin updater.
 *
 * Hooks into WordPress's native plugin update transient system
 * to check for updates via the FluentCart API.
 *
 * @package WPE\Favorites
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Licensing;

defined('ABSPATH') || exit;

final class PluginUpdater {

    private string $cache_key;

    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * @param array<string, mixed> $config Updater configuration.
     */
    public function __construct(array $config = []) {
        $defaults = [
            'type'                 => 'plugin',
            'slug'                 => '',
            'item_id'              => '',
            'basename'             => '',
            'version'              => '',
            'api_url'              => '',
            'license_key'          => '',
            'license_key_callback' => '',
        ];

        $config = wp_parse_args($config, $defaults);

        $this->config    = $config;
        $this->cache_key = 'fsl_' . md5($config['basename'] . '_' . $config['item_id']) . '_version_info';

        if ($config['type'] === 'plugin') {
            add_filter('pre_set_site_transient_update_plugins', [$this, 'checkPluginUpdate']);
            add_filter('plugins_api', [$this, 'pluginsApiFilter'], 10, 3);
        }
    }

    /**
     * Check for plugin updates.
     *
     * @param mixed $transient_data Update transient data.
     * @return mixed
     */
    public function checkPluginUpdate(mixed $transient_data): mixed {
        global $pagenow;

        if (!is_object($transient_data)) {
            $transient_data = new \stdClass();
        }

        if ('plugins.php' === $pagenow && is_network_admin()) {
            return $transient_data;
        }

        if (!empty($transient_data->response) && !empty($transient_data->response[$this->config['basename']])) {
            return $transient_data;
        }

        $version_info = $this->getVersionInfo();

        if (false !== $version_info && is_object($version_info) && isset($version_info->new_version)) {
            unset($version_info->sections);

            if (version_compare($this->config['version'], $version_info->new_version, '<')) {
                $transient_data->response[$this->config['basename']] = $version_info;
            } else {
                $transient_data->no_update[$this->config['basename']] = $version_info;
            }

            $transient_data->last_checked                          = time();
            $transient_data->checked[$this->config['basename']] = $this->config['version'];
        }

        return $transient_data;
    }

    /**
     * Filter the plugins API response for plugin information modal.
     *
     * @param mixed       $data   Plugin data.
     * @param string      $action The action type.
     * @param object|null $args   Arguments.
     * @return mixed
     */
    public function pluginsApiFilter(mixed $data, string $action = '', ?object $args = null): mixed {
        if ('plugin_information' !== $action || !$args) {
            return $data;
        }

        $slug = $this->config['slug'];

        if (!isset($args->slug) || $args->slug !== $slug) {
            return $data;
        }

        $data = $this->getVersionInfo();

        if (is_wp_error($data)) {
            return $data;
        }

        if (!$data) {
            return new \WP_Error('no_data', 'No data found for this plugin');
        }

        return $data;
    }

    /**
     * Get cached version info.
     *
     * @return mixed
     */
    private function getCachedVersionInfo(): mixed {
        global $pagenow;

        if ('update-core.php' === $pagenow || ($pagenow === 'plugin-install.php' && !empty($_GET['plugin']))) {
            return false;
        }

        return get_transient($this->cache_key);
    }

    /**
     * Cache version info.
     *
     * @param mixed $value Version info to cache.
     */
    private function setCachedVersionInfo(mixed $value): void {
        if (!$value) {
            return;
        }

        set_transient($this->cache_key, $value, 3 * HOUR_IN_SECONDS);
    }

    /**
     * Get version info (cached or remote).
     *
     * @return mixed
     */
    private function getVersionInfo(): mixed {
        $versionInfo = $this->getCachedVersionInfo();

        if (false === $versionInfo) {
            $versionInfo = $this->getRemoteVersionInfo();
            $this->setCachedVersionInfo($versionInfo);
        }

        return $versionInfo;
    }

    /**
     * Fetch version info from the FluentCart API.
     *
     * @return \stdClass|false
     */
    private function getRemoteVersionInfo(): \stdClass|false {
        $fullUrl = add_query_arg(['fluent-cart' => 'get_license_version'], $this->config['api_url']);

        $payload = [
            'item_id'          => $this->config['item_id'],
            'current_version'  => $this->config['version'],
            'site_url'         => is_multisite() ? network_home_url() : home_url(),
            'platform_version' => get_bloginfo('version'),
            'server_version'   => phpversion(),
            'license_key'      => $this->config['license_key'],
        ];

        if (empty($payload['license_key']) && !empty($this->config['license_key_callback'])) {
            $payload['license_key'] = call_user_func($this->config['license_key_callback']);
        }

        /** @var array<string, mixed> $payload */
        $payload = apply_filters('fluent_sl/updater_payload_' . $this->config['slug'], $payload, $this->config);

        $response = wp_remote_post($fullUrl, [
            'timeout'   => 15,
            'body'      => $payload,
            'sslverify' => false,
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $responseBody = wp_remote_retrieve_body($response);

        if (empty($responseBody)) {
            return false;
        }

        $versionInfo = json_decode($responseBody);

        if (null === $versionInfo || !is_object($versionInfo) || !isset($versionInfo->new_version)) {
            return false;
        }

        $versionInfo->plugin = $this->config['basename'];
        $versionInfo->slug   = $this->config['slug'];

        if (!empty($versionInfo->sections)) {
            $versionInfo->sections = (array) $versionInfo->sections;
        }

        if (!isset($versionInfo->banners)) {
            $versionInfo->banners = [];
        } else {
            $versionInfo->banners = (array) $versionInfo->banners;
        }

        if (!isset($versionInfo->icons)) {
            $versionInfo->icons = [];
        } else {
            $versionInfo->icons = (array) $versionInfo->icons;
        }

        return $versionInfo;
    }
}
