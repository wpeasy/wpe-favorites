<?php
/**
 * GitHub-based plugin updater for public repositories.
 *
 * Integrates with WordPress' native update system to check for new
 * releases on GitHub and offer one-click updates from the Plugins page.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Updater;

defined('ABSPATH') || exit;

final class GitHubUpdater {

    /** GitHub repository owner. */
    private const OWNER = 'wpeasy';

    /** GitHub repository name. */
    private const REPO = 'wpe-favorites';

    /** Plugin slug (directory name). */
    private const SLUG = 'wpe-favorites';

    /** Transient key for cached release data. */
    private const CACHE_KEY = 'wpef_github_release';

    /** Cache duration in seconds (12 hours). */
    private const CACHE_TTL = 43200;

    /** GitHub API base URL. */
    private const API_BASE = 'https://api.github.com';

    /**
     * Register WordPress hooks.
     */
    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_updates']);
        add_filter('plugins_api', [self::class, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection', [self::class, 'fix_directory_name'], 10, 4);
        add_action('upgrader_process_complete', [self::class, 'after_update'], 10, 2);
    }

    /* ------------------------------------------------------------------ */
    /*  Update check                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Inject update data into the WordPress transient when a newer
     * release exists on GitHub.
     *
     * @param object $transient WordPress update transient.
     * @return object Modified transient.
     */
    public static function check_for_updates(object $transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = self::get_cached_release();

        if (!$release) {
            return $transient;
        }

        $github_version = self::normalize_version($release['tag_name'] ?? '');

        if (!version_compare($github_version, WPEF_VERSION, '>')) {
            return $transient;
        }

        $download_url = self::get_zip_url($release);

        if (!$download_url) {
            return $transient;
        }

        $plugin_file = plugin_basename(WPEF_PLUGIN_FILE);

        $transient->response[$plugin_file] = (object) [
            'slug'         => self::SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => $github_version,
            'url'          => $release['html_url'] ?? '',
            'package'      => $download_url,
            'icons'        => [],
            'banners'      => [],
            'tested'       => '',
            'requires'     => '6.5',
            'requires_php' => '8.0',
        ];

        return $transient;
    }

    /* ------------------------------------------------------------------ */
    /*  Plugin info popup                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Supply plugin details for the "View Details" popup on the Plugins page.
     *
     * @param false|object $result Default result.
     * @param string       $action API action.
     * @param object       $args   Request arguments.
     * @return false|object
     */
    public static function plugin_info(false|object $result, string $action, object $args): false|object {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $release = self::get_cached_release();

        if (!$release) {
            return $result;
        }

        $changelog = '';
        if (!empty($release['body'])) {
            $changelog = nl2br(esc_html($release['body']));
        }

        return (object) [
            'name'          => 'WPE Favorites',
            'slug'          => self::SLUG,
            'version'       => self::normalize_version($release['tag_name'] ?? ''),
            'author'        => '<a href="https://github.com/wpeasy">Alan Blair</a>',
            'homepage'      => $release['html_url'] ?? '',
            'requires'      => '6.5',
            'requires_php'  => '8.0',
            'downloaded'    => 0,
            'last_updated'  => $release['published_at'] ?? '',
            'sections'      => [
                'description' => __('User favorites system for WordPress Posts and Custom Post Types.', 'wpef'),
                'changelog'   => $changelog,
            ],
            'download_link' => self::get_zip_url($release),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Directory name fix                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Ensure the extracted ZIP directory matches the plugin slug.
     *
     * GitHub release ZIPs may extract to a different directory name.
     * WordPress expects the directory to match the existing plugin folder.
     *
     * @param string $source     Extracted source path.
     * @param string $remote     Remote source path.
     * @param object $upgrader   WP_Upgrader instance.
     * @param array  $hook_extra Extra context data.
     * @return string Corrected source path.
     */
    public static function fix_directory_name(string $source, string $remote, object $upgrader, array $hook_extra): string {
        global $wp_filesystem;

        if (!isset($hook_extra['plugin'])) {
            return $source;
        }

        if ($hook_extra['plugin'] !== plugin_basename(WPEF_PLUGIN_FILE)) {
            return $source;
        }

        $source_dir = basename(untrailingslashit($source));

        if ($source_dir === self::SLUG) {
            return $source;
        }

        $new_source = trailingslashit(dirname(untrailingslashit($source))) . self::SLUG . '/';

        if ($wp_filesystem && $wp_filesystem->move($source, $new_source)) {
            return $new_source;
        }

        return $source;
    }

    /* ------------------------------------------------------------------ */
    /*  Post-update cleanup                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Clear cached release data after the plugin is updated.
     *
     * @param object $upgrader WP_Upgrader instance.
     * @param array  $options  Update context.
     */
    public static function after_update(object $upgrader, array $options): void {
        if (($options['action'] ?? '') !== 'update' || ($options['type'] ?? '') !== 'plugin') {
            return;
        }

        $our_plugin = plugin_basename(WPEF_PLUGIN_FILE);

        if (in_array($our_plugin, $options['plugins'] ?? [], true)) {
            delete_transient(self::CACHE_KEY);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  GitHub API                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Get the latest release, from cache or GitHub API.
     *
     * @return array<string, mixed>|null Release data or null on failure.
     */
    private static function get_cached_release(): ?array {
        $cached = get_transient(self::CACHE_KEY);

        if ($cached !== false) {
            return $cached;
        }

        $url = sprintf('%s/repos/%s/%s/releases/latest', self::API_BASE, self::OWNER, self::REPO);

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept'               => 'application/vnd.github+json',
                'User-Agent'           => 'WPE-Favorites/' . WPEF_VERSION,
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || empty($data['tag_name'])) {
            return null;
        }

        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);

        return $data;
    }

    /**
     * Find the .zip asset URL from a release.
     *
     * @param array<string, mixed> $release GitHub release data.
     * @return string|null Browser download URL or null.
     */
    private static function get_zip_url(array $release): ?string {
        foreach ($release['assets'] ?? [] as $asset) {
            if (isset($asset['name']) && str_ends_with($asset['name'], '.zip')) {
                return $asset['browser_download_url'] ?? null;
            }
        }

        return null;
    }

    /**
     * Strip leading 'v' from a version tag.
     *
     * @param string $version Raw version string.
     * @return string Normalized version.
     */
    private static function normalize_version(string $version): string {
        return ltrim($version, 'vV');
    }
}
