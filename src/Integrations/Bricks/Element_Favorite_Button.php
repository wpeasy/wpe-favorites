<?php
/**
 * Bricks element: Favorite Button.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Integrations\Bricks;

use WPE\Favorites\Modules\Shortcode;
use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

if (!defined('BRICKS_VERSION')) {
    return;
}

/**
 * Favorite Button element for Bricks Builder.
 */
class Element_Favorite_Button extends \Bricks\Element {

    /**
     * Element category.
     *
     * @var string
     */
    public $category = 'favorites';

    /**
     * Element name (unique identifier).
     *
     * @var string
     */
    public $name = 'wpef-favorite-button';

    /**
     * Element icon (dashicon class).
     *
     * @var string
     */
    public $icon = 'ti-heart';

    /**
     * Element scripts to enqueue.
     *
     * @var string[]
     */
    public $scripts = [];

    /**
     * Element label.
     */
    public function get_label(): string {
        return esc_html__('Favorite Button', 'wpef');
    }

    /**
     * Element keywords for search.
     *
     * @return string[]
     */
    public function get_keywords(): array {
        return ['favorite', 'heart', 'like', 'bookmark', 'wpef'];
    }

    /**
     * Register control groups.
     */
    public function set_control_groups(): void {
        $this->control_groups['inactive'] = [
            'title' => esc_html__('Inactive', 'wpef'),
        ];

        $this->control_groups['active'] = [
            'title' => esc_html__('Active', 'wpef'),
        ];

        $this->control_groups['hover'] = [
            'title' => esc_html__('Hover', 'wpef'),
        ];
    }

    /**
     * Register element controls.
     */
    public function set_controls(): void {
        $this->controls['post_id'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Post ID', 'wpef'),
            'type'        => 'number',
            'placeholder' => esc_html__('Current post', 'wpef'),
            'description' => esc_html__('Leave empty to use the current post ID.', 'wpef'),
        ];

        // -- Inactive -----------------------------------------------

        $this->controls['label'] = [
            'group'       => 'inactive',
            'label'       => esc_html__('Label', 'wpef'),
            'type'        => 'text',
            'placeholder' => esc_html__('e.g. Add to Favorites', 'wpef'),
        ];

        $this->controls['labelTypography'] = [
            'group' => 'inactive',
            'label' => esc_html__('Label Typography', 'wpef'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.wpef-button__state--inactive .wpef-button__label',
                ],
            ],
        ];

        $this->controls['icon'] = [
            'group' => 'inactive',
            'label' => esc_html__('Icon', 'wpef'),
            'type'  => 'icon',
            'css'   => [
                [
                    'property' => 'color',
                    'selector' => '.wpef-button__state--inactive .wpef-button__icon',
                ],
            ],
        ];

        // -- Active -------------------------------------------------

        $this->controls['active_label'] = [
            'group'       => 'active',
            'label'       => esc_html__('Label', 'wpef'),
            'type'        => 'text',
            'placeholder' => esc_html__('e.g. Remove from Favorites', 'wpef'),
        ];

        $this->controls['activeLabelTypography'] = [
            'group' => 'active',
            'label' => esc_html__('Label Typography', 'wpef'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.wpef-button__state--active .wpef-button__label',
                ],
            ],
        ];

        $this->controls['active_icon'] = [
            'group' => 'active',
            'label' => esc_html__('Icon', 'wpef'),
            'type'  => 'icon',
            'css'   => [
                [
                    'property' => 'color',
                    'selector' => '.wpef-button__state--active .wpef-button__icon',
                ],
            ],
        ];

        // -- Hover --------------------------------------------------

        $this->controls['hoverTypography'] = [
            'group' => 'hover',
            'label' => esc_html__('Typography', 'wpef'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.wpef-button:hover',
                ],
            ],
        ];
    }

    /**
     * Render the element output.
     */
    public function render(): void {
        Plugin::enqueue_assets();

        $settings = $this->settings;
        $post_id  = !empty($settings['post_id']) ? absint($settings['post_id']) : 0;

        $shortcode_atts = [
            'post_id' => $post_id ?: get_the_ID(),
        ];

        // Labels (support dynamic data tags).
        if (!empty($settings['label'])) {
            $shortcode_atts['label'] = $this->render_dynamic_data($settings['label']);
        }
        if (!empty($settings['active_label'])) {
            $shortcode_atts['active_label'] = $this->render_dynamic_data($settings['active_label']);
        }

        // Icons — render via Bricks Element helper (handles font icons + SVG).
        if (!empty($settings['icon'])) {
            $shortcode_atts['icon_html'] = self::render_icon($settings['icon']);
        }
        if (!empty($settings['active_icon'])) {
            $shortcode_atts['active_icon_html'] = self::render_icon($settings['active_icon']);
        }

        $root_classes = ['wpef-bricks-favorite'];
        $this->set_attribute('_root', 'class', $root_classes);

        echo "<div {$this->render_attributes('_root')}>";
        echo Shortcode::render($shortcode_atts);
        echo '</div>';
    }

    /**
     * Builder preview (static HTML).
     */
    public static function render_builder(): void {
        ?>
        <script type="text/x-template" id="tmpl-bricks-element-wpef-favorite-button">
            <div class="wpef-bricks-favorite">
                <button class="wpef-button" aria-label="Toggle favorite">
                    <span class="wpef-icon wpef-icon--heart"></span>
                </button>
            </div>
        </script>
        <?php
    }
}
