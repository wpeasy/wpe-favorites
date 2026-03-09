<?php
/**
 * Bricks element: Global Favorite Count.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Integrations\Bricks;

use WPE\Favorites\Favorites;
use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

if (!defined('BRICKS_VERSION')) {
    return;
}

/**
 * Global Favorite Count element for Bricks Builder.
 */
class Element_Global_Count extends \Bricks\Element {

    /** @var string */
    public $category = 'favorites';

    /** @var string */
    public $name = 'wpef-global-count';

    /** @var string */
    public $icon = 'ti-world';

    /** @var string[] */
    public $scripts = [];

    public function get_label(): string {
        return esc_html__('Global Favorite Count', 'wpef');
    }

    /** @return string[] */
    public function get_keywords(): array {
        return ['favorite', 'count', 'global', 'total', 'wpef'];
    }

    public function set_controls(): void {
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

        $this->controls['tag'] = [
            'tab'     => 'content',
            'label'   => esc_html__('HTML Tag', 'wpef'),
            'type'    => 'select',
            'options' => [
                'span' => 'span',
                'div'  => 'div',
                'p'    => 'p',
            ],
            'default' => 'span',
        ];
    }

    public function render(): void {
        Plugin::enqueue_assets();

        $settings = $this->settings;
        $source   = $settings['postTypeSource'] ?? 'select';
        $tag      = !empty($settings['tag']) ? $settings['tag'] : 'span';

        // Resolve post types based on source.
        $post_types = [];

        if ($source === 'dynamic' && !empty($settings['postTypeDynamic'])) {
            $resolved = sanitize_key($this->render_dynamic_data($settings['postTypeDynamic']));
            if ($resolved !== '') {
                $post_types = [$resolved];
            }
        } elseif (!empty($settings['post_type']) && is_array($settings['post_type'])) {
            $post_types = array_map('sanitize_key', $settings['post_type']);
        }

        if (!empty($post_types)) {
            $count = 0;
            foreach ($post_types as $pt) {
                $count += Favorites::global_count_by_type($pt);
            }
        } else {
            $count = Favorites::global_count();
        }

        $this->set_attribute('_root', 'class', ['wpef-count', 'wpef-count--global']);

        // Data attributes for JS live-count updates.
        $data_attr = 'data-wpef-count="global"';
        if (count($post_types) === 1) {
            $data_attr .= ' data-wpef-post-type="' . esc_attr($post_types[0]) . '"';
        }

        echo "<{$tag} {$this->render_attributes('_root')}>";
        echo '<span class="wpef-count__value" ' . $data_attr . '>' . esc_html((string) $count) . '</span>';
        echo "</{$tag}>";
    }

    public static function render_builder(): void {
        ?>
        <script type="text/x-template" id="tmpl-bricks-element-wpef-global-count">
            <span class="wpef-count wpef-count--global">
                <span class="wpef-count__value">0</span>
            </span>
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
