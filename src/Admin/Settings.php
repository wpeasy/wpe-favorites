<?php
/**
 * Admin settings page.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Admin;

defined('ABSPATH') || exit;

final class Settings {

    private const OPTION_KEY = 'wpef_settings';
    private const NONCE_ACTION = 'wpef_save_settings';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu_page']);
        add_action('admin_init', [self::class, 'handle_save']);
    }

    /**
     * Add the Favorites menu page.
     */
    public static function add_menu_page(): void {
        add_menu_page(
            __('Favorites', 'wpef'),
            __('Favorites', 'wpef'),
            'manage_options',
            'wpef-settings',
            [self::class, 'render_page'],
            'dashicons-heart',
            30
        );
    }

    /**
     * Get saved settings.
     *
     * @return array{enabled_post_types: string[], limits_per_type: array<string, int>, max_favorites: int}
     */
    public static function get_settings(): array {
        $defaults = [
            'enabled_post_types' => [],
            'limits_per_type'    => [],
            'max_favorites'      => 0,
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (!is_array($saved)) {
            return $defaults;
        }

        return wp_parse_args($saved, $defaults);
    }

    /**
     * Get the per-type limit for a given post type.
     *
     * @param string $post_type Post type slug.
     * @return int 0 = unlimited.
     */
    public static function get_limit_for_type(string $post_type): int {
        $settings = self::get_settings();
        return $settings['limits_per_type'][$post_type] ?? 0;
    }

    /**
     * Get the global max favorites limit.
     *
     * @return int 0 = unlimited.
     */
    public static function get_max_favorites(): int {
        $settings = self::get_settings();
        return (int) ($settings['max_favorites'] ?? 0);
    }

    /**
     * Get post types that have favorites enabled.
     *
     * Falls back to all public types if none are explicitly configured.
     *
     * @return string[]
     */
    public static function get_enabled_post_types(): array {
        $settings = self::get_settings();
        $enabled  = $settings['enabled_post_types'];

        // If nothing saved yet, default to Posts and Pages.
        if (empty($enabled) && !get_option(self::OPTION_KEY)) {
            return ['post', 'page'];
        }

        return $enabled;
    }

    /**
     * Handle form submission.
     */
    public static function handle_save(): void {
        if (!isset($_POST['wpef_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field($_POST['wpef_nonce']), self::NONCE_ACTION)) {
            wp_die(__('Security check failed.', 'wpef'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'wpef'));
        }

        $raw_types     = isset($_POST['wpef_post_types']) && is_array($_POST['wpef_post_types'])
            ? $_POST['wpef_post_types']
            : [];
        $public_types  = get_post_types(['public' => true], 'names');
        $enabled_types = array_values(array_intersect(
            array_map('sanitize_key', $raw_types),
            array_values($public_types)
        ));

        // Per-type limits.
        $raw_limits     = isset($_POST['wpef_limits']) && is_array($_POST['wpef_limits'])
            ? $_POST['wpef_limits']
            : [];
        $limits_per_type = [];
        foreach ($raw_limits as $slug => $val) {
            $slug = sanitize_key($slug);
            $val  = absint($val);
            if ($val > 0 && in_array($slug, array_values($public_types), true)) {
                $limits_per_type[$slug] = $val;
            }
        }

        // Global max favorites.
        $max_favorites = isset($_POST['wpef_max_favorites']) ? absint($_POST['wpef_max_favorites']) : 0;

        update_option(self::OPTION_KEY, [
            'enabled_post_types' => $enabled_types,
            'limits_per_type'    => $limits_per_type,
            'max_favorites'      => $max_favorites,
        ]);

        add_settings_error('wpef_settings', 'wpef_saved', __('Settings saved.', 'wpef'), 'updated');
        set_transient('settings_errors', get_settings_errors('wpef_settings'), 30);

        wp_safe_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }

    /**
     * Render the settings page.
     */
    public static function render_page(): void {
        $settings        = self::get_settings();
        $enabled         = $settings['enabled_post_types'];
        $limits_per_type = $settings['limits_per_type'];
        $max_favorites   = $settings['max_favorites'];
        $has_saved       = (bool) get_option(self::OPTION_KEY);
        $public_types    = get_post_types(['public' => true], 'objects');

        // Show admin notices.
        if (isset($_GET['settings-updated'])) {
            settings_errors('wpef_settings');
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Favorites Settings', 'wpef'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION, 'wpef_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enabled Post Types', 'wpef'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text">
                                        <?php esc_html_e('Enabled Post Types', 'wpef'); ?>
                                    </legend>
                                    <table class="widefat striped" style="max-width: 500px;">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Post Type', 'wpef'); ?></th>
                                                <th style="width: 120px;"><?php esc_html_e('Max per User', 'wpef'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($public_types as $type): ?>
                                                <?php
                                                $checked = $has_saved
                                                    ? in_array($type->name, $enabled, true)
                                                    : in_array($type->name, ['post', 'page'], true);
                                                $limit = $limits_per_type[$type->name] ?? '';
                                                ?>
                                                <tr>
                                                    <td>
                                                        <label>
                                                            <input
                                                                type="checkbox"
                                                                name="wpef_post_types[]"
                                                                value="<?php echo esc_attr($type->name); ?>"
                                                                <?php checked($checked); ?>
                                                            />
                                                            <?php echo esc_html($type->labels->name); ?>
                                                            <code style="margin-left: 4px; font-size: 12px; color: #666;">
                                                                <?php echo esc_html($type->name); ?>
                                                            </code>
                                                        </label>
                                                    </td>
                                                    <td>
                                                        <input
                                                            type="number"
                                                            name="wpef_limits[<?php echo esc_attr($type->name); ?>]"
                                                            value="<?php echo esc_attr($limit); ?>"
                                                            min="1"
                                                            placeholder="<?php esc_attr_e('Unlimited', 'wpef'); ?>"
                                                            style="width: 100%;"
                                                        />
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <p class="description">
                                        <?php esc_html_e('Select which post types support the favorites feature. Optionally set a per-type limit.', 'wpef'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wpef_max_favorites"><?php esc_html_e('Max Favorites per User', 'wpef'); ?></label>
                            </th>
                            <td>
                                <input
                                    type="number"
                                    id="wpef_max_favorites"
                                    name="wpef_max_favorites"
                                    value="<?php echo esc_attr($max_favorites ? $max_favorites : ''); ?>"
                                    min="1"
                                    placeholder="<?php esc_attr_e('Unlimited', 'wpef'); ?>"
                                    class="small-text"
                                />
                                <p class="description">
                                    <?php esc_html_e('Maximum total favorites across all post types. Leave empty for unlimited.', 'wpef'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'wpef')); ?>
            </form>
        </div>
        <?php
    }
}
