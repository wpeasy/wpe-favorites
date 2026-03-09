<?php
/**
 * [wpef_favorites] shortcode.
 *
 * A loop shortcode that sets up WP_Query for the current user's favorites.
 * Inner content is rendered once per post with full post data available.
 *
 * Usage:
 *   [wpef_favorites]
 *     <h2>[wpef_field field="title"]</h2>
 *   [/wpef_favorites]
 *
 *   [wpef_favorites post_type="product" posts_per_page="10"]
 *     <div class="card">
 *       <a href="[wpef_field field="url"]">[wpef_field field="title"]</a>
 *       [wpef_button]
 *     </div>
 *   [/wpef_favorites]
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
     * Register shortcodes.
     */
    public static function init(): void {
        add_shortcode('wpef_favorites', [self::class, 'render']);
        add_shortcode('wpef_field', [self::class, 'render_field']);
    }

    /**
     * Render the favorites loop.
     *
     * When used as a wrapping shortcode [wpef_favorites]...[/wpef_favorites],
     * the inner content is rendered for each post. When used as a self-closing
     * shortcode, a default list of linked titles is rendered.
     *
     * @param array|string $atts    Shortcode attributes.
     * @param string|null  $content Inner content template.
     * @return string HTML output.
     */
    public static function render(array|string $atts = [], ?string $content = null): string {
        $atts = shortcode_atts([
            'post_type'      => '',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'class'          => '',
            'no_results'     => '',
        ], $atts, 'wpef_favorites');

        $user_id = get_current_user_id();

        if ($user_id === 0) {
            return $atts['no_results'] !== '' ? esc_html($atts['no_results']) : '';
        }

        $favorites = Favorites::get($user_id);

        if (empty($favorites)) {
            return $atts['no_results'] !== '' ? esc_html($atts['no_results']) : '';
        }

        $post_type = sanitize_key($atts['post_type']);

        if ($post_type !== '') {
            $favorites = array_filter(
                $favorites,
                fn(array $fav): bool => $fav['postType'] === $post_type
            );
        }

        $post_ids = array_column($favorites, 'postId');

        if (empty($post_ids)) {
            return $atts['no_results'] !== '' ? esc_html($atts['no_results']) : '';
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
            return $atts['no_results'] !== '' ? esc_html($atts['no_results']) : '';
        }

        $has_content = $content !== null && trim($content) !== '';
        $output      = '';

        if ($has_content) {
            // Wrapping shortcode: render inner content for each post.
            while ($query->have_posts()) {
                $query->the_post();
                $output .= do_shortcode($content);
            }
        } else {
            // Self-closing fallback: render a simple linked list.
            $css_class = 'wpef-favorites-list';
            if ($atts['class'] !== '') {
                $css_class .= ' ' . esc_attr($atts['class']);
            }

            $output = '<ul class="' . $css_class . '">';

            while ($query->have_posts()) {
                $query->the_post();
                $output .= sprintf(
                    '<li class="wpef-favorites-list__item"><a href="%s">%s</a></li>',
                    esc_url((string) get_permalink()),
                    esc_html((string) get_the_title())
                );
            }

            $output .= '</ul>';
        }

        wp_reset_postdata();

        return $output;
    }

    /**
     * Render a post field inside a [wpef_favorites] loop.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string Field value.
     */
    public static function render_field(array|string $atts = []): string {
        $atts = shortcode_atts([
            'field' => 'title',
        ], $atts, 'wpef_field');

        $post = get_post();

        if (!$post) {
            return '';
        }

        return match ($atts['field']) {
            'title'     => esc_html((string) get_the_title($post)),
            'url'       => esc_url((string) get_permalink($post)),
            'excerpt'   => esc_html((string) get_the_excerpt($post)),
            'date'      => esc_html((string) get_the_date('', $post)),
            'id'        => (string) $post->ID,
            'post_type' => esc_html($post->post_type),
            'thumbnail' => (string) get_the_post_thumbnail($post, 'thumbnail'),
            default     => '',
        };
    }
}
