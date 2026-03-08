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
        $this->controls['post_type'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Post Type', 'wpef'),
            'type'        => 'select',
            'options'     => self::get_post_type_options(),
            'placeholder' => esc_html__('All post types', 'wpef'),
            'description' => esc_html__('Leave empty to count all favorites.', 'wpef'),
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

        $settings  = $this->settings;
        $post_type = !empty($settings['post_type']) ? sanitize_key($settings['post_type']) : '';
        $tag       = !empty($settings['tag']) ? $settings['tag'] : 'span';

        $count = $post_type !== ''
            ? Favorites::global_count_by_type($post_type)
            : Favorites::global_count();

        $this->set_attribute('_root', 'class', ['wpef-count', 'wpef-count--global']);

        $data_attr = 'data-wpef-count="global"';
        if ($post_type !== '') {
            $data_attr .= ' data-wpef-post-type="' . esc_attr($post_type) . '"';
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
