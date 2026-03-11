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
    private const PAGE_SLUG = 'wpef-settings';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu_page']);
        add_action('admin_init', [self::class, 'handle_save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_settings_assets']);
    }

    /**
     * Add the Favorites menu page.
     */
    public static function add_menu_page(): void {
        add_menu_page(
            __('Favorites', 'wpef'),
            __('Favorites', 'wpef'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page'],
            'dashicons-heart',
            30
        );
    }

    /**
     * Enqueue the Svelte settings app assets on settings page only.
     *
     * @param string $hook_suffix The admin page hook suffix.
     */
    public static function enqueue_settings_assets(string $hook_suffix): void {
        if (!str_contains($hook_suffix, self::PAGE_SLUG)) {
            return;
        }

        wp_enqueue_style(
            'wpef-admin-settings',
            WPEF_PLUGIN_URL . 'assets/admin/settings.css',
            [],
            WPEF_VERSION
        );

        wp_enqueue_script(
            'wpef-admin-settings',
            WPEF_PLUGIN_URL . 'assets/admin/settings.js',
            [],
            WPEF_VERSION,
            true
        );

        $settings    = self::get_settings();
        $rules       = $settings['post_type_rules'];
        $public_types = get_post_types(['public' => true], 'objects');

        // If no rules yet, migrate from old enabled_post_types or create default.
        if (empty($rules)) {
            $rules = self::migrate_to_rules($settings['enabled_post_types']);
        }

        $post_type_options = [];
        foreach ($public_types as $type) {
            $post_type_options[] = [
                'value' => $type->name,
                'label' => $type->labels->name . ' (' . $type->name . ')',
            ];
        }

        $role_options = [];
        foreach (wp_roles()->get_names() as $slug => $name) {
            $role_options[] = [
                'value' => $slug,
                'label' => translate_user_role($name),
            ];
        }

        // Build limits map: slug => int (only non-zero).
        $limits_map = [];
        foreach ($settings['limits_per_type'] as $slug => $val) {
            if ($val > 0) {
                $limits_map[$slug] = (int) $val;
            }
        }

        wp_add_inline_script(
            'wpef-admin-settings',
            'window.WPEF_SETTINGS = ' . wp_json_encode([
                'rules'         => array_values($rules),
                'roles'         => $role_options,
                'postTypes'     => $post_type_options,
                'limitsPerType' => (object) $limits_map,
                'maxFavorites'  => (int) $settings['max_favorites'],
            ]) . ';',
            'before'
        );
    }

    /**
     * Get saved settings.
     *
     * @return array{enabled_post_types: string[], post_type_rules: array, limits_per_type: array<string, int>, max_favorites: int}
     */
    public static function get_settings(): array {
        $defaults = [
            'enabled_post_types' => [],
            'post_type_rules'    => [],
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
     * Get enabled post types for a specific user based on role rules.
     *
     * Processes rules top-to-bottom: start with empty set, include/exclude per rule.
     * Exclude rules have highest priority — once a type is excluded, it must be
     * explicitly re-included by a later rule to be available again.
     * Anonymous users ($user_id = 0) match any rule with the 'all' role,
     * including the default rule.
     *
     * @param int $user_id User ID (0 for anonymous).
     * @return string[]
     */
    public static function get_enabled_post_types_for_user(int $user_id = 0): array {
        $settings = self::get_settings();
        $rules    = $settings['post_type_rules'];

        // Migration: if no rules exist but old enabled_post_types has data.
        if (empty($rules)) {
            $rules = self::migrate_to_rules($settings['enabled_post_types']);
        }

        // If still no rules, default to post + page.
        if (empty($rules)) {
            return ['post', 'page'];
        }

        // Get user roles.
        $user_roles = [];
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if ($user) {
                $user_roles = $user->roles;
            }
        }

        $public_types = array_values(get_post_types(['public' => true], 'names'));
        $enabled      = $public_types;

        foreach ($rules as $rule) {
            if (!isset($rule['roles'], $rule['type'], $rule['postTypes'])) {
                continue;
            }

            // Check if user matches this rule.
            $matches = false;
            if (in_array('all', $rule['roles'], true)) {
                $matches = true;
            } elseif ($user_id > 0 && !empty(array_intersect($rule['roles'], $user_roles))) {
                $matches = true;
            }

            if (!$matches) {
                continue;
            }

            // Filter to valid public post types.
            $rule_types = array_intersect($rule['postTypes'], $public_types);

            if ($rule['type'] === 'include') {
                $enabled = array_unique(array_merge($enabled, $rule_types));
            } elseif ($rule['type'] === 'exclude') {
                $enabled = array_diff($enabled, $rule_types);
            }
        }

        return array_values($enabled);
    }

    /**
     * Get post types that have favorites enabled for the current user.
     *
     * @return string[]
     */
    public static function get_enabled_post_types(): array {
        return self::get_enabled_post_types_for_user(get_current_user_id());
    }

    /**
     * Migrate old enabled_post_types array to a single include rule.
     *
     * @param string[] $enabled_post_types Old enabled post types.
     * @return array Rule array.
     */
    private static function migrate_to_rules(array $enabled_post_types): array {
        if (empty($enabled_post_types)) {
            // No old data either — check if settings were ever saved.
            if (!get_option(self::OPTION_KEY)) {
                // Fresh install: include all public types.
                $all_public = array_values(get_post_types(['public' => true], 'names'));
                return [
                    [
                        'id'        => wp_generate_uuid4(),
                        'name'      => 'Default',
                        'roles'     => ['all'],
                        'type'      => 'include',
                        'postTypes' => $all_public,
                    ],
                ];
            }
            return [];
        }

        return [
            [
                'id'        => wp_generate_uuid4(),
                'name'      => 'Default',
                'roles'     => ['all'],
                'type'      => 'include',
                'postTypes' => $enabled_post_types,
            ],
        ];
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

        // Post type rules from Svelte app.
        $rules = [];
        if (isset($_POST['wpef_rules'])) {
            $raw_rules = json_decode(sanitize_text_field(wp_unslash($_POST['wpef_rules'])), true);
            if (is_array($raw_rules)) {
                $public_types = array_values(get_post_types(['public' => true], 'names'));
                $valid_roles  = array_keys(wp_roles()->get_names());

                foreach ($raw_rules as $raw_rule) {
                    if (!is_array($raw_rule)) {
                        continue;
                    }

                    $type = $raw_rule['type'] ?? '';
                    if (!in_array($type, ['include', 'exclude'], true)) {
                        continue;
                    }

                    // Sanitize roles.
                    $roles = [];
                    if (isset($raw_rule['roles']) && is_array($raw_rule['roles'])) {
                        foreach ($raw_rule['roles'] as $role) {
                            $role = sanitize_key($role);
                            if ($role === 'all' || in_array($role, $valid_roles, true)) {
                                $roles[] = $role;
                            }
                        }
                    }

                    // Sanitize post types.
                    $post_types = [];
                    if (isset($raw_rule['postTypes']) && is_array($raw_rule['postTypes'])) {
                        foreach ($raw_rule['postTypes'] as $pt) {
                            $pt = sanitize_key($pt);
                            if (in_array($pt, $public_types, true)) {
                                $post_types[] = $pt;
                            }
                        }
                    }

                    $rules[] = [
                        'id'        => sanitize_key($raw_rule['id'] ?? wp_generate_uuid4()),
                        'name'      => sanitize_text_field($raw_rule['name'] ?? ''),
                        'roles'     => $roles,
                        'type'      => $type,
                        'postTypes' => $post_types,
                    ];
                }
            }
        }

        // Per-type limits (JSON from Svelte app).
        $limits_per_type = [];
        if (isset($_POST['wpef_limits_json'])) {
            $raw_limits = json_decode(sanitize_text_field(wp_unslash($_POST['wpef_limits_json'])), true);
            if (is_array($raw_limits)) {
                $public_types = array_values(get_post_types(['public' => true], 'names'));
                foreach ($raw_limits as $slug => $val) {
                    $slug = sanitize_key($slug);
                    $val  = absint($val);
                    if ($val > 0 && in_array($slug, $public_types, true)) {
                        $limits_per_type[$slug] = $val;
                    }
                }
            }
        }

        // Global max favorites.
        $max_favorites = isset($_POST['wpef_max_favorites']) ? absint($_POST['wpef_max_favorites']) : 0;

        update_option(self::OPTION_KEY, [
            'post_type_rules' => $rules,
            'limits_per_type' => $limits_per_type,
            'max_favorites'   => $max_favorites,
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
        // Show admin notices.
        if (isset($_GET['settings-updated'])) {
            settings_errors('wpef_settings');
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Favorites Settings', 'wpef'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION, 'wpef_nonce'); ?>
                <div id="wpef-settings-app"></div>
                <?php submit_button(__('Save Settings', 'wpef')); ?>
            </form>
        </div>
        <?php
    }
}
