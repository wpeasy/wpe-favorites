<?php
/**
 * [wpef_post_types] shortcode.
 *
 * Renders a list of post types that have favorites enabled.
 * Useful for displaying available favorite categories or building filter UIs.
 *
 * Usage:
 *   [wpef_post_types]
 *   [wpef_post_types link="archive"]
 *   [wpef_post_types class="my-types"]
 *
 * @package WPE\Favorites
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Modules;

use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

final class PostTypesLoop {

    /**
     * Register the shortcode.
     */
    public static function init(): void {
        add_shortcode('wpef_post_types', [self::class, 'render']);
    }

    /**
     * Render the post types list.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render(array|string $atts = []): string {
        $atts = shortcode_atts([
            'link'  => '',
            'class' => '',
        ], $atts, 'wpef_post_types');

        $types = Plugin::get_supported_post_types();

        if (empty($types)) {
            return '';
        }

        $css_class = 'wpef-post-types-list';
        if ($atts['class'] !== '') {
            $css_class .= ' ' . esc_attr($atts['class']);
        }

        $output = '<ul class="' . $css_class . '">';

        foreach ($types as $type_name) {
            $type_obj = get_post_type_object($type_name);

            if (!$type_obj) {
                continue;
            }

            $label = esc_html($type_obj->labels->name);

            if ($atts['link'] === 'archive') {
                $url = get_post_type_archive_link($type_name);
                $content = $url
                    ? '<a href="' . esc_url($url) . '">' . $label . '</a>'
                    : $label;
            } else {
                $content = $label;
            }

            $output .= sprintf(
                '<li class="wpef-post-types-list__item" data-wpef-post-type="%s">%s</li>',
                esc_attr($type_name),
                $content
            );
        }

        $output .= '</ul>';

        return $output;
    }
}
