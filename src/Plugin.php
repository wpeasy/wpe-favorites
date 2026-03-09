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
        Admin\Settings::init();
        Admin\Documentation::init();
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

        $user_id = get_current_user_id();

        wp_add_inline_script(
            'wpef-favorites',
            'window.WPEF = ' . wp_json_encode([
                'restUrl'    => esc_url_raw(rest_url('wpef/v1')),
                'nonce'      => wp_create_nonce('wp_rest'),
                'isLoggedIn' => $user_id > 0,
            ]) . ';',
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
