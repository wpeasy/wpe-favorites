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
     * @return array{enabled_post_types: string[]}
     */
    public static function get_settings(): array {
        $defaults = [
            'enabled_post_types' => [],
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (!is_array($saved)) {
            return $defaults;
        }

        return wp_parse_args($saved, $defaults);
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

        update_option(self::OPTION_KEY, [
            'enabled_post_types' => $enabled_types,
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
        $settings     = self::get_settings();
        $enabled      = $settings['enabled_post_types'];
        $has_saved    = (bool) get_option(self::OPTION_KEY);
        $public_types = get_post_types(['public' => true], 'objects');

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
                                    <?php foreach ($public_types as $type): ?>
                                        <?php
                                        $checked = $has_saved
                                            ? in_array($type->name, $enabled, true)
                                            : in_array($type->name, ['post', 'page'], true);
                                        ?>
                                        <label style="display: block; margin-bottom: 8px;">
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
                                    <?php endforeach; ?>
                                    <p class="description">
                                        <?php esc_html_e('Select which post types support the favorites feature.', 'wpef'); ?>
                                    </p>
                                </fieldset>
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
