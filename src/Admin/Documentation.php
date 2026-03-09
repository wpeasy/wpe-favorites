<?php
/**
 * Admin documentation page.
 *
 * Renders a Svelte-powered documentation UI as a submenu
 * under the main Favorites menu.
 *
 * @package WPE\Favorites
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Admin;

defined('ABSPATH') || exit;

final class Documentation {

    private const PAGE_SLUG = 'wpef-docs';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_submenu_page']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Add Documentation submenu under Favorites.
     */
    public static function add_submenu_page(): void {
        add_submenu_page(
            'wpef-settings',
            __('Documentation', 'wpef'),
            __('Documentation', 'wpef'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    /**
     * Enqueue the Svelte docs app assets on our page only.
     *
     * @param string $hook_suffix The admin page hook suffix.
     */
    public static function enqueue_assets(string $hook_suffix): void {
        if (!str_contains($hook_suffix, self::PAGE_SLUG)) {
            return;
        }

        wp_enqueue_style(
            'wpef-admin-docs',
            WPEF_PLUGIN_URL . 'assets/admin/docs.css',
            [],
            WPEF_VERSION
        );

        wp_enqueue_script(
            'wpef-admin-docs',
            WPEF_PLUGIN_URL . 'assets/admin/docs.js',
            [],
            WPEF_VERSION,
            true
        );
    }

    /**
     * Render the documentation page shell.
     */
    public static function render_page(): void {
        ?>
        <div class="wrap">
            <div id="wpef-docs-app"></div>
        </div>
        <?php
    }
}
