<?php
/**
 * [wpef_favorites] shortcode.
 *
 * Renders a list of the current user's favorited posts.
 * Works in Gutenberg, classic editor, or any shortcode-capable context.
 *
 * Usage:
 *   [wpef_favorites]
 *   [wpef_favorites post_type="product"]
 *   [wpef_favorites posts_per_page="10" orderby="date" order="DESC"]
 *
 * @package WPE\Favorites
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Modules;

use WPE\Favorites\Favorites;

defined('ABSPATH') || exit;

final class FavoritesLoop {

    /**
     * Register the shortcode.
     */
    public static function init(): void {
        add_shortcode('wpef_favorites', [self::class, 'render']);
    }

    /**
     * Render the favorites list.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render(array|string $atts = []): string {
        $atts = shortcode_atts([
            'post_type'      => '',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'class'          => '',
        ], $atts, 'wpef_favorites');

        $user_id = get_current_user_id();

        if ($user_id === 0) {
            return '';
        }

        $favorites = Favorites::get($user_id);

        if (empty($favorites)) {
            return '';
        }

        $post_type = sanitize_key($atts['post_type']);

        // Filter by post type if specified.
        if ($post_type !== '') {
            $favorites = array_filter(
                $favorites,
                fn(array $fav): bool => $fav['postType'] === $post_type
            );
        }

        $post_ids = array_column($favorites, 'postId');

        if (empty($post_ids)) {
            return '';
        }

        $per_page = (int) $atts['posts_per_page'];
        $orderby  = sanitize_key($atts['orderby']);
        $order    = strtoupper(sanitize_text_field($atts['order']));

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = ['title', 'date', 'modified', 'ID', 'rand', 'post__in'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'title';
        }

        // Determine post types to query.
        $query_post_types = $post_type !== ''
            ? [$post_type]
            : array_unique(array_column($favorites, 'postType'));

        $query = new \WP_Query([
            'post_type'      => $query_post_types,
            'post__in'       => $post_ids,
            'posts_per_page' => $per_page,
            'orderby'        => $orderby,
            'order'          => $order,
            'post_status'    => 'publish',
        ]);

        if (!$query->have_posts()) {
            return '';
        }

        $css_class = 'wpef-favorites-list';
        if ($atts['class'] !== '') {
            $css_class .= ' ' . esc_attr($atts['class']);
        }

        $output = '<ul class="' . $css_class . '">';

        while ($query->have_posts()) {
            $query->the_post();
            $output .= sprintf(
                '<li class="wpef-favorites-list__item"><a href="%s">%s</a></li>',
                esc_url(get_permalink()),
                esc_html(get_the_title())
            );
        }

        wp_reset_postdata();

        $output .= '</ul>';

        return $output;
    }
}
