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
     * Accepts shortcode attributes plus optional keys passed programmatically:
     * - label, active_label: text labels per state
     * - icon_class, active_icon_class: CSS class icons per state (shortcode)
     * - icon_html, active_icon_html: pre-rendered icon HTML (Bricks/programmatic only)
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render(array|string $atts = []): string {
        // Preserve programmatic-only keys before shortcode_atts strips them.
        $icon_html        = is_array($atts) ? ($atts['icon_html'] ?? '') : '';
        $active_icon_html = is_array($atts) ? ($atts['active_icon_html'] ?? '') : '';

        $atts = shortcode_atts([
            'post_id'           => get_the_ID(),
            'label'             => '',
            'active_label'      => '',
            'icon_class'        => '',
            'active_icon_class' => '',
        ], $atts, 'wpef_button');

        /**
         * Filter shortcode attributes before rendering.
         *
         * @param array $atts Parsed shortcode attributes.
         */
        $atts = apply_filters('wpef_button_atts', $atts);

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

        $label             = sanitize_text_field($atts['label']);
        $active_label      = sanitize_text_field($atts['active_label']);
        $icon_class        = sanitize_text_field($atts['icon_class']);
        $active_icon_class = sanitize_text_field($atts['active_icon_class']);

        $has_custom = $label || $active_label || $icon_class || $active_icon_class || $icon_html || $active_icon_html;

        $post_title = get_the_title($post);

        // Default output — identical to original markup.
        if (!$has_custom) {
            return sprintf(
                '<button class="wpef-button" data-wpef-post-id="%d" data-wpef-post-type="%s" data-wpef-post-title="%s" aria-label="%s" aria-pressed="false">'
                . '<span class="wpef-button__icon wpef-icon wpef-icon--heart"></span>'
                . '</button>',
                $post_id,
                esc_attr($post_type),
                esc_attr($post_title),
                esc_attr(sprintf(__('Add %s to favorites', 'wpef'), $post_title))
            );
        }

        // Dual-state markup.
        $btn_classes = 'wpef-button';
        if ($label || $active_label) {
            $btn_classes .= ' wpef-button--has-label';
        }

        $inactive_icon = self::render_icon($icon_html, $icon_class, 'wpef-icon wpef-icon--heart');
        $active_icon   = self::render_icon($active_icon_html, $active_icon_class, 'wpef-icon wpef-icon--heart-filled');

        $has_label = $label || $active_label;

        // aria-label only when icon-only (no visible text to serve as accessible name).
        $aria_attr = $has_label ? '' : sprintf(
            ' aria-label="%s"',
            esc_attr(sprintf(__('Add %s to favorites', 'wpef'), $post_title))
        );

        $html  = sprintf(
            '<button class="%s" data-wpef-post-id="%d" data-wpef-post-type="%s" data-wpef-post-title="%s"%s aria-pressed="false">',
            esc_attr($btn_classes),
            $post_id,
            esc_attr($post_type),
            esc_attr($post_title),
            $aria_attr
        );

        // Inactive state.
        $html .= '<span class="wpef-button__state wpef-button__state--inactive">';
        $html .= $inactive_icon;
        if ($label) {
            $html .= '<span class="wpef-button__label">' . esc_html($label) . '</span>';
        }
        $html .= '</span>';

        // Active state.
        $html .= '<span class="wpef-button__state wpef-button__state--active">';
        $html .= $active_icon;
        if ($active_label || $label) {
            $html .= '<span class="wpef-button__label">' . esc_html($active_label ?: $label) . '</span>';
        }
        $html .= '</span>';

        $html .= '</button>';

        return $html;
    }

    /**
     * Render an icon span.
     *
     * Priority: pre-rendered HTML > CSS class > default CSS mask icon.
     *
     * @param string $icon_html   Pre-rendered icon HTML (from Bricks or programmatic).
     * @param string $icon_class  CSS class string (e.g. "fa-regular fa-heart").
     * @param string $default_cls Default wpef-icon class(es) to use as fallback.
     * @return string Icon HTML.
     */
    private static function render_icon(string $icon_html, string $icon_class, string $default_cls): string {
        if ($icon_html) {
            return '<span class="wpef-button__icon">' . $icon_html . '</span>';
        }

        if ($icon_class) {
            return '<span class="wpef-button__icon"><i class="' . esc_attr($icon_class) . '"></i></span>';
        }

        return '<span class="wpef-button__icon ' . esc_attr($default_cls) . '"></span>';
    }
}
