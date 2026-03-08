<?php
/**
 * Count shortcodes.
 *
 * [wpef_user_count]                        — current user's total favorites
 * [wpef_user_count post_type="product"]    — current user's favorites for a post type
 * [wpef_post_count]                        — how many users favorited the current post
 * [wpef_post_count post_id="42"]           — how many users favorited post 42
 * [wpef_global_count]                      — total favorites across all users
 * [wpef_global_count post_type="product"]  — global favorites for a post type
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Modules;

use WPE\Favorites\Favorites;
use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

final class CountShortcodes {

    /**
     * Register shortcodes.
     */
    public static function init(): void {
        add_shortcode('wpef_user_count', [self::class, 'render_user_count']);
        add_shortcode('wpef_post_count', [self::class, 'render_post_count']);
        add_shortcode('wpef_global_count', [self::class, 'render_global_count']);
    }

    /**
     * Render the current user's favorite count.
     *
     * Attributes:
     *   post_type — optional post type to filter by.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public static function render_user_count(array|string $atts = []): string {
        Plugin::enqueue_assets();

        $atts = shortcode_atts([
            'post_type' => '',
        ], $atts, 'wpef_user_count');

        $user_id = get_current_user_id();

        if ($user_id === 0) {
            $count = 0;
        } else {
            $post_type = sanitize_key($atts['post_type']);
            $count = $post_type !== ''
                ? Favorites::user_count_by_type($user_id, $post_type)
                : Favorites::user_count($user_id);
        }

        $post_type = sanitize_key($atts['post_type']);
        $data_attr = 'data-wpef-count="user"';
        if ($post_type !== '') {
            $data_attr .= ' data-wpef-post-type="' . esc_attr($post_type) . '"';
        }

        return '<span class="wpef-count__value" ' . $data_attr . '>' . esc_html((string) $count) . '</span>';
    }

    /**
     * Render how many users favorited a post.
     *
     * Attributes:
     *   post_id — optional post ID (defaults to current post).
     *
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public static function render_post_count(array|string $atts = []): string {
        Plugin::enqueue_assets();

        $atts = shortcode_atts([
            'post_id' => get_the_ID(),
        ], $atts, 'wpef_post_count');

        $post_id = absint($atts['post_id']);
        $count   = $post_id > 0 ? Favorites::get_post_count($post_id) : 0;

        return '<span class="wpef-count__value" data-wpef-count="post" data-wpef-post-id="'
            . esc_attr((string) $post_id) . '">'
            . esc_html((string) $count) . '</span>';
    }

    /**
     * Render the global favorite count across all users.
     *
     * Attributes:
     *   post_type — optional post type to filter by.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public static function render_global_count(array|string $atts = []): string {
        Plugin::enqueue_assets();

        $atts = shortcode_atts([
            'post_type' => '',
        ], $atts, 'wpef_global_count');

        $post_type = sanitize_key($atts['post_type']);
        $count = $post_type !== ''
            ? Favorites::global_count_by_type($post_type)
            : Favorites::global_count();

        $data_attr = 'data-wpef-count="global"';
        if ($post_type !== '') {
            $data_attr .= ' data-wpef-post-type="' . esc_attr($post_type) . '"';
        }

        return '<span class="wpef-count__value" ' . $data_attr . '>' . esc_html((string) $count) . '</span>';
    }
}
