<?php
/**
 * [wpef_clear] shortcode.
 *
 * Renders a button that clears all (or filtered) favorites for the current user.
 *
 * @package WPE\Favorites
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Modules;

use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

final class ClearShortcode {

    /**
     * Register the shortcode.
     */
    public static function init(): void {
        add_shortcode('wpef_clear', [self::class, 'render']);
    }

    /**
     * Render the clear button.
     *
     * Attributes:
     *   post_type — optional comma-separated post types to clear.
     *   label     — button label text.
     *   confirm   — confirmation text (enables double opt-in).
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render(array|string $atts = []): string {
        $atts = shortcode_atts([
            'post_type' => '',
            'label'     => __('Clear Favorites', 'wpef'),
            'confirm'   => '',
        ], $atts, 'wpef_clear');

        /**
         * Filter shortcode attributes before rendering.
         *
         * @param array $atts Parsed shortcode attributes.
         */
        $atts = apply_filters('wpef_clear_atts', $atts);

        Plugin::enqueue_assets();

        $label      = sanitize_text_field($atts['label']);
        $confirm    = sanitize_text_field($atts['confirm']);
        $post_types = self::parse_post_types($atts['post_type']);

        $data_attrs = '';
        if (!empty($post_types)) {
            $data_attrs .= ' data-wpef-clear-types="' . esc_attr(implode(',', $post_types)) . '"';
        }
        if ($confirm !== '') {
            $data_attrs .= ' data-wpef-clear-confirm="' . esc_attr($confirm) . '"';
        }

        return sprintf(
            '<button class="wpef-clear" data-wpef-clear%s>%s</button>',
            $data_attrs,
            esc_html($label)
        );
    }

    /**
     * Parse a comma-separated post type string into a sanitized array.
     *
     * @param string $raw Raw post_type attribute value.
     * @return string[]
     */
    private static function parse_post_types(string $raw): array {
        if ($raw === '') {
            return [];
        }

        $types = array_map('sanitize_key', array_map('trim', explode(',', $raw)));
        return array_filter($types);
    }
}
