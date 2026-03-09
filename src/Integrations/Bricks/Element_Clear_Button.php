<?php
/**
 * Bricks element: Clear Favorites Button.
 *
 * @package WPE\Favorites
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Integrations\Bricks;

use WPE\Favorites\Modules\ClearShortcode;
use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

if (!defined('BRICKS_VERSION')) {
    return;
}

/**
 * Clear Favorites Button element for Bricks Builder.
 */
class Element_Clear_Button extends \Bricks\Element {

    /** @var string */
    public $category = 'favorites';

    /** @var string */
    public $name = 'wpef-clear-button';

    /** @var string */
    public $icon = 'ti-trash';

    /** @var string[] */
    public $scripts = [];

    public function get_label(): string {
        return esc_html__('Clear Favorites', 'wpef');
    }

    /** @return string[] */
    public function get_keywords(): array {
        return ['favorite', 'clear', 'reset', 'remove', 'all', 'wpef'];
    }

    /**
     * Register control groups.
     */
    public function set_control_groups(): void {
        $this->control_groups['button'] = [
            'title' => esc_html__('Button', 'wpef'),
        ];

        $this->control_groups['confirmation'] = [
            'title' => esc_html__('Confirmation', 'wpef'),
        ];
    }

    /**
     * Register element controls.
     */
    public function set_controls(): void {
        // -- Button group -----------------------------------------------

        $this->controls['label'] = [
            'group'       => 'button',
            'label'       => esc_html__('Label', 'wpef'),
            'type'        => 'text',
            'default'     => esc_html__('Clear Favorites', 'wpef'),
            'placeholder' => esc_html__('Clear Favorites', 'wpef'),
        ];

        $this->controls['buttonTypography'] = [
            'group' => 'button',
            'label' => esc_html__('Typography', 'wpef'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.wpef-clear',
                ],
            ],
        ];

        // -- Post Type Filtering ----------------------------------------

        $this->controls['postTypeSource'] = [
            'tab'     => 'content',
            'label'   => esc_html__('Post Type Source', 'wpef'),
            'type'    => 'select',
            'options' => [
                'select'  => esc_html__('Select', 'wpef'),
                'dynamic' => esc_html__('Dynamic', 'wpef'),
            ],
            'default' => 'select',
        ];

        $this->controls['post_type'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Post Types', 'wpef'),
            'type'        => 'select',
            'options'     => self::get_post_type_options(),
            'multiple'    => true,
            'default'     => [],
            'placeholder' => esc_html__('All post types', 'wpef'),
            'required'    => [['postTypeSource', '!=', 'dynamic']],
        ];

        $this->controls['postTypeDynamic'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Post Type (Dynamic)', 'wpef'),
            'type'        => 'text',
            'placeholder' => esc_html__('e.g. {cf_post_type}', 'wpef'),
            'required'    => [['postTypeSource', '=', 'dynamic']],
        ];

        // -- Confirmation group -----------------------------------------

        $this->controls['confirmText'] = [
            'group'       => 'confirmation',
            'label'       => esc_html__('Confirmation Text', 'wpef'),
            'type'        => 'text',
            'default'     => esc_html__('Are you sure?', 'wpef'),
            'placeholder' => esc_html__('Are you sure?', 'wpef'),
            'description' => esc_html__('Text shown on the button after the first click. The user must click again to confirm.', 'wpef'),
        ];

        $this->controls['confirmTypography'] = [
            'group' => 'confirmation',
            'label' => esc_html__('Typography', 'wpef'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.wpef-clear--confirming',
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
        $source   = $settings['postTypeSource'] ?? 'select';

        // Resolve post types.
        $post_types = [];

        if ($source === 'dynamic' && !empty($settings['postTypeDynamic'])) {
            $resolved = sanitize_key($this->render_dynamic_data($settings['postTypeDynamic']));
            if ($resolved !== '') {
                $post_types = [$resolved];
            }
        } elseif (!empty($settings['post_type']) && is_array($settings['post_type'])) {
            $post_types = array_map('sanitize_key', $settings['post_type']);
        }

        $shortcode_atts = [];

        if (!empty($settings['label'])) {
            $shortcode_atts['label'] = $this->render_dynamic_data($settings['label']);
        }

        if (!empty($post_types)) {
            $shortcode_atts['post_type'] = implode(',', $post_types);
        }

        if (!empty($settings['confirmText'])) {
            $shortcode_atts['confirm'] = $this->render_dynamic_data($settings['confirmText']);
        }

        $root_classes = ['wpef-bricks-clear'];
        $this->set_attribute('_root', 'class', $root_classes);

        echo "<div {$this->render_attributes('_root')}>";
        echo ClearShortcode::render($shortcode_atts);
        echo '</div>';
    }

    /**
     * Builder preview (static HTML).
     */
    public static function render_builder(): void {
        ?>
        <script type="text/x-template" id="tmpl-bricks-element-wpef-clear-button">
            <div class="wpef-bricks-clear">
                <button class="wpef-clear">Clear Favorites</button>
            </div>
        </script>
        <?php
    }

    /**
     * Build post type options from public post types.
     *
     * @return array<string, string>
     */
    private static function get_post_type_options(): array {
        $types   = get_post_types(['public' => true], 'objects');
        $options = [];

        foreach ($types as $type) {
            $options[$type->name] = $type->labels->name;
        }

        return $options;
    }
}
