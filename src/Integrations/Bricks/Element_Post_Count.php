<?php
/**
 * Bricks element: Post Favorite Count.
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
 * Post Favorite Count element for Bricks Builder.
 */
class Element_Post_Count extends \Bricks\Element {

    /** @var string */
    public $category = 'favorites';

    /** @var string */
    public $name = 'wpef-post-count';

    /** @var string */
    public $icon = 'ti-stats-up';

    /** @var string[] */
    public $scripts = [];

    public function get_label(): string {
        return esc_html__('Post Favorite Count', 'wpef');
    }

    /** @return string[] */
    public function get_keywords(): array {
        return ['favorite', 'count', 'post', 'total', 'wpef'];
    }

    public function set_controls(): void {
        $this->controls['postIdSource'] = [
            'tab'     => 'content',
            'label'   => esc_html__('Post ID Source', 'wpef'),
            'type'    => 'select',
            'options' => [
                'select'  => esc_html__('Select', 'wpef'),
                'dynamic' => esc_html__('Dynamic', 'wpef'),
            ],
            'default' => 'select',
        ];

        $this->controls['post_id'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Post ID', 'wpef'),
            'type'        => 'number',
            'placeholder' => esc_html__('Current post', 'wpef'),
            'description' => esc_html__('Leave empty to use the current post ID.', 'wpef'),
            'required'    => [['postIdSource', '!=', 'dynamic']],
        ];

        $this->controls['postIdDynamic'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Post ID (Dynamic)', 'wpef'),
            'type'        => 'text',
            'placeholder' => esc_html__('e.g. {cf_post_id}', 'wpef'),
            'required'    => [['postIdSource', '=', 'dynamic']],
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
        $source   = $settings['postIdSource'] ?? 'select';
        $tag      = !empty($settings['tag']) ? $settings['tag'] : 'span';

        // Resolve post ID based on source.
        if ($source === 'dynamic' && !empty($settings['postIdDynamic'])) {
            $post_id = absint($this->render_dynamic_data($settings['postIdDynamic']));
        } else {
            $post_id = !empty($settings['post_id']) ? absint($settings['post_id']) : 0;
        }

        if ($post_id === 0) {
            $post_id = get_the_ID();
        }

        $count = Favorites::get_post_count($post_id);

        $this->set_attribute('_root', 'class', ['wpef-count', 'wpef-count--post']);

        echo "<{$tag} {$this->render_attributes('_root')}>";
        echo '<span class="wpef-count__value" data-wpef-count="post" data-wpef-post-id="' . esc_attr((string) $post_id) . '">' . esc_html((string) $count) . '</span>';
        echo "</{$tag}>";
    }

    public static function render_builder(): void {
        ?>
        <script type="text/x-template" id="tmpl-bricks-element-wpef-post-count">
            <span class="wpef-count wpef-count--post">
                <span class="wpef-count__value">0</span>
            </span>
        </script>
        <?php
    }
}
