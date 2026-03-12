<?php
/**
 * Plugin bootstrap.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites;

defined('ABSPATH') || exit;

final class Plugin {

    /**
     * Registered modules.
     *
     * @var array<string, object>
     */
    private static array $modules = [];

    /**
     * Initialize the plugin.
     */
    public static function init(): void {
        // Always register the top-level admin menu so submenus have a parent.
        add_action('admin_menu', [Admin\Settings::class, 'add_menu_page']);

        // Licensing — always initialized.
        self::init_licensing();

        // Documentation — always accessible, even unlicensed.
        Admin\Documentation::init();

        // All features always load — license only gates settings and updates.
        if (self::is_licensed()) {
            Admin\Settings::init();
        } else {
            add_action('admin_notices', [self::class, 'render_license_notice']);
        }

        REST\FavoritesController::init();
        Modules\Shortcode::init();
        Modules\CountShortcodes::init();
        Modules\FavoritesLoop::init();
        Modules\PostTypesLoop::init();
        Modules\ClearShortcode::init();
        Modules\Cleanup::init();
        add_action('after_setup_theme', [Integrations\Bricks\BricksIntegration::class, 'init']);

        add_action('wp_enqueue_scripts', [self::class, 'register_frontend_assets']);
    }

    /**
     * Initialize FluentCart licensing and settings page.
     */
    private static function init_licensing(): void {
        $licensing = new Licensing\FluentLicensing();
        $licensing->register([
            'version'      => WPEF_VERSION,
            'item_id'      => WPEF_LICENSE_ITEM_ID,
            'basename'     => plugin_basename(WPEF_PLUGIN_FILE),
            'api_url'      => WPEF_LICENSE_API_URL,
            'slug'         => WPEF_LICENSE_SLUG,
            'settings_key' => WPEF_LICENSE_SETTINGS_KEY,
        ]);

        $licenseSettings = new Licensing\LicenseSettings();
        $licenseSettings->register($licensing, [
            'menu_title'   => __('License', 'wpef'),
            'page_title'   => __('WPE Favorites - License', 'wpef'),
            'title'        => __('WPE Favorites - License', 'wpef'),
            'purchase_url' => 'https://alanblair.co/?fluent-cart=instant_checkout&item_id=9&quantity=1',
            'account_url'  => 'https://alanblair.co/my-account/',
            'plugin_name'  => 'WPE Favorites',
        ]);

        $licenseSettings->addPage([
            'type'        => 'submenu',
            'page_title'  => __('WPE Favorites - License', 'wpef'),
            'menu_title'  => __('License', 'wpef'),
            'parent_slug' => 'wpef-settings',
            'capability'  => 'manage_options',
        ]);
    }

    /**
     * Check if the plugin has a valid license.
     *
     * @return bool
     */
    public static function is_licensed(): bool {
        // Always licensed on local/dev sites.
        if (self::is_local_dev_site()) {
            return true;
        }

        if (!Licensing\FluentLicensing::isRegistered()) {
            return false;
        }

        try {
            $licensing = Licensing\FluentLicensing::getInstance();
            $status    = $licensing->getStatus();

            return ($status['status'] ?? '') === 'valid';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Detect local/development sites that bypass license checks.
     *
     * @return bool
     */
    private static function is_local_dev_site(): bool {
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        // Check TLDs.
        $local_tlds = ['.local', '.test', '.dev', '.invalid', '.localhost'];
        foreach ($local_tlds as $tld) {
            if (str_ends_with($host, $tld)) {
                return true;
            }
        }

        // Check IP ranges (private/reserved).
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        // Check keywords.
        $dev_keywords = ['localhost', 'staging'];
        foreach ($dev_keywords as $keyword) {
            if (stripos($host, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Show admin notice when plugin is unlicensed.
     */
    public static function render_license_notice(): void {
        $screen = get_current_screen();

        // Only show on our plugin pages.
        if (!$screen || !str_contains($screen->id, 'wpef')) {
            return;
        }

        // Don't show on the license page itself.
        if (str_contains($screen->id, 'manage-license')) {
            return;
        }

        $license_url = admin_url('admin.php?page=' . WPEF_LICENSE_SLUG . '-manage-license');

        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('WPE Favorites requires a valid license to access settings and receive updates.', 'wpef'),
            esc_url($license_url),
            esc_html__('Activate your license', 'wpef')
        );
    }

    /**
     * Register a module.
     *
     * @param string $name   Module identifier.
     * @param object $module Module instance.
     */
    public static function register_module(string $name, object $module): void {
        self::$modules[$name] = $module;
    }

    /**
     * Get a registered module.
     *
     * @param string $name Module identifier.
     * @return object|null
     */
    public static function get_module(string $name): ?object {
        return self::$modules[$name] ?? null;
    }

    /**
     * Register frontend assets without enqueuing.
     *
     * Assets are enqueued on demand via enqueue_assets() when a
     * shortcode or Bricks element actually renders on the page.
     */
    public static function register_frontend_assets(): void {
        wp_register_style(
            'wpef-favorites',
            WPEF_PLUGIN_URL . 'assets/css/favorites.css',
            [],
            WPEF_VERSION
        );

        wp_register_script(
            'wpef-favorites',
            WPEF_PLUGIN_URL . 'assets/js/favorites.js',
            [],
            WPEF_VERSION,
            true
        );

        $user_id  = get_current_user_id();
        $settings = Admin\Settings::get_settings();

        $limits = [];
        if (!empty($settings['limits_per_type'])) {
            $limits['perType'] = $settings['limits_per_type'];
        }
        if (!empty($settings['max_favorites'])) {
            $limits['total'] = $settings['max_favorites'];
        }

        $config = [
            'restUrl'    => esc_url_raw(rest_url('wpef/v1')),
            'nonce'      => wp_create_nonce('wp_rest'),
            'isLoggedIn' => $user_id > 0,
            'userId'     => $user_id,
        ];

        if (!empty($limits)) {
            $config['limits'] = $limits;
        }

        wp_add_inline_script(
            'wpef-favorites',
            'window.WPEF = ' . wp_json_encode($config) . ';',
            'before'
        );
    }

    /**
     * Enqueue frontend assets on demand.
     *
     * Call this from any shortcode or Bricks element render method.
     */
    public static function enqueue_assets(): void {
        wp_enqueue_style('wpef-favorites');
        wp_enqueue_script('wpef-favorites');
    }

    /**
     * Get supported post types based on admin settings.
     *
     * @return string[]
     */
    public static function get_supported_post_types(): array {
        $types = Admin\Settings::get_enabled_post_types();

        return apply_filters('wpef_supported_post_types', $types);
    }
}
