<?php
/**
 * [wpef_button] shortcode.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Modules;

use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

final class Shortcode {

    /**
     * Register the shortcode.
     */
    public static function init(): void {
        add_shortcode('wpef_button', [self::class, 'render']);
    }

    /**
     * Render the favorite button.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render(array|string $atts = []): string {
        $atts = shortcode_atts([
            'post_id' => get_the_ID(),
        ], $atts, 'wpef_button');

        $post_id = absint($atts['post_id']);

        if ($post_id === 0) {
            return '';
        }

        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        Plugin::enqueue_assets();

        $post_type = $post->post_type;

        return sprintf(
            '<button class="wpef-button" data-wpef-post-id="%d" data-wpef-post-type="%s" aria-label="%s">'
            . '<span class="wpef-icon wpef-icon--heart"></span>'
            . '</button>',
            $post_id,
            esc_attr($post_type),
            esc_attr__('Toggle favorite', 'wpef')
        );
    }
}
