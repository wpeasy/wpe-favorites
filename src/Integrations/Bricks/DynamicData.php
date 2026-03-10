<?php
/**
 * Bricks dynamic data tags and conditions.
 *
 * Registers custom tokens and conditional visibility rules for Bricks Builder.
 *
 * @package WPE\Favorites
 * @since   1.0.4
 */

declare(strict_types=1);

namespace WPE\Favorites\Integrations\Bricks;

use WPE\Favorites\Favorites;

defined('ABSPATH') || exit;

final class DynamicData {

    private const GROUP = 'WPE Favorites';
    private const CONDITIONALS_GROUP = 'wpef_favorites';

    /**
     * Initialize dynamic data hooks.
     */
    public static function init(): void {
        if (!defined('BRICKS_VERSION')) {
            return;
        }

        // Dynamic data tags.
        add_filter('bricks/dynamic_tags_list', [self::class, 'register_tags']);
        add_filter('bricks/dynamic_data/render_tag', [self::class, 'render_tag'], 20, 3);
        add_filter('bricks/dynamic_data/render_content', [self::class, 'render_content'], 20, 3);
        add_filter('bricks/frontend/render_data', [self::class, 'render_content'], 20, 2);

        // Conditions.
        add_filter('bricks/conditions/groups', [self::class, 'register_conditions_group']);
        add_filter('bricks/conditions/options', [self::class, 'register_conditions']);
        add_filter('bricks/conditions/result', [self::class, 'evaluate_condition'], 10, 3);
    }

    /* ------------------------------------------------------------------ */
    /*  Dynamic data tags                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Register dynamic data tags.
     *
     * @param array $tags Existing tags.
     * @return array Modified tags.
     */
    public static function register_tags(array $tags): array {
        $tags[] = [
            'name'  => '{wpef_user_count}',
            'label' => 'User Favorite Count (current post type)',
            'group' => self::GROUP,
        ];

        $tags[] = [
            'name'  => '{wpef_user_count:all}',
            'label' => 'User Favorite Count (all types)',
            'group' => self::GROUP,
        ];

        $tags[] = [
            'name'  => '{wpef_user_count:post_type}',
            'label' => 'User Favorite Count (by Post Type)',
            'group' => self::GROUP,
        ];

        $tags[] = [
            'name'  => '{wpef_post_count}',
            'label' => 'Post Favorite Count',
            'group' => self::GROUP,
        ];

        $tags[] = [
            'name'  => '{wpef_post_count:post_id}',
            'label' => 'Post Favorite Count (by Post ID)',
            'group' => self::GROUP,
        ];

        $tags[] = [
            'name'  => '{wpef_global_count}',
            'label' => 'Global Favorite Count',
            'group' => self::GROUP,
        ];

        $tags[] = [
            'name'  => '{wpef_global_count:post_type}',
            'label' => 'Global Favorite Count (by Post Type)',
            'group' => self::GROUP,
        ];

        return $tags;
    }

    /**
     * Render a single dynamic data tag.
     *
     * @param mixed    $tag     Tag to render.
     * @param \WP_Post $post    Current post.
     * @param string   $context Render context.
     * @return mixed Rendered value or original tag.
     */
    public static function render_tag($tag, $post, string $context = 'text') {
        if (!is_string($tag)) {
            return $tag;
        }

        $clean = str_replace(['{', '}'], '', $tag);

        // {wpef_user_count} or {wpef_user_count:post_type}
        if ($clean === 'wpef_user_count' || strpos($clean, 'wpef_user_count:') === 0) {
            $user_id = get_current_user_id();
            if ($user_id === 0) {
                return '0';
            }

            $param = self::parse_param($clean, 'wpef_user_count');

            // Default: current post type. Use "all" for total across all types.
            if ($param === '' || $param === 'post_type') {
                $param = get_post_type() ?: 'post';
            }

            if ($param === 'all') {
                return (string) Favorites::user_count($user_id);
            }

            return (string) Favorites::user_count_by_type($user_id, $param);
        }

        // {wpef_post_count} or {wpef_post_count:42}
        if ($clean === 'wpef_post_count' || strpos($clean, 'wpef_post_count:') === 0) {
            $param = self::parse_param($clean, 'wpef_post_count');
            $post_id = $param !== '' ? absint($param) : self::get_current_post_id($post);

            return $post_id > 0 ? (string) Favorites::get_post_count($post_id) : '0';
        }

        // {wpef_global_count} or {wpef_global_count:post_type}
        if ($clean === 'wpef_global_count' || strpos($clean, 'wpef_global_count:') === 0) {
            $post_type = self::parse_param($clean, 'wpef_global_count');

            return $post_type !== ''
                ? (string) Favorites::global_count_by_type($post_type)
                : (string) Favorites::global_count();
        }

        return $tag;
    }

    /**
     * Render tags embedded in content strings.
     *
     * @param string   $content Content with tags.
     * @param \WP_Post $post    Current post.
     * @param string   $context Render context.
     * @return string Content with tags replaced.
     */
    public static function render_content(string $content, $post, string $context = 'text'): string {
        if (!is_string($content) || strpos($content, '{wpef_') === false) {
            return $content;
        }

        // Match {wpef_*} tokens.
        return (string) preg_replace_callback(
            '/\{(wpef_(?:user_count|post_count|global_count)(?::[^}]*)?)\}/',
            function (array $matches) use ($post, $context) {
                $rendered = self::render_tag('{' . $matches[1] . '}', $post, $context);
                return is_string($rendered) ? $rendered : $matches[0];
            },
            $content
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Conditions                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Register the conditions group.
     *
     * @param array $groups Existing groups.
     * @return array Modified groups.
     */
    public static function register_conditions_group(array $groups): array {
        $groups[] = [
            'name'  => self::CONDITIONALS_GROUP,
            'label' => esc_html__('WPE Favorites', 'wpef'),
        ];

        return $groups;
    }

    /**
     * Register condition options.
     *
     * @param array $options Existing options.
     * @return array Modified options.
     */
    public static function register_conditions(array $options): array {
        $options[] = [
            'key'     => 'wpef_is_favorited',
            'label'   => esc_html__('Post Is Favorited', 'wpef'),
            'group'   => self::CONDITIONALS_GROUP,
            'compare' => [
                'type'        => 'select',
                'options'     => [
                    '==' => esc_html__('is true', 'wpef'),
                    '!=' => esc_html__('is false', 'wpef'),
                ],
                'placeholder' => esc_html__('is true', 'wpef'),
            ],
        ];

        $options[] = [
            'key'     => 'wpef_user_has_favorites',
            'label'   => esc_html__('User Has Favorites', 'wpef'),
            'group'   => self::CONDITIONALS_GROUP,
            'compare' => [
                'type'        => 'select',
                'options'     => [
                    '==' => esc_html__('is true', 'wpef'),
                    '!=' => esc_html__('is false', 'wpef'),
                ],
                'placeholder' => esc_html__('is true', 'wpef'),
            ],
        ];

        return $options;
    }

    /**
     * Evaluate a condition.
     *
     * @param bool   $result    Current result.
     * @param string $key       Condition key.
     * @param array  $condition Condition data.
     * @return bool Evaluated result.
     */
    public static function evaluate_condition(bool $result, string $key, array $condition): bool {
        $compare = $condition['compare'] ?? '==';

        switch ($key) {
            case 'wpef_is_favorited':
                $user_id = get_current_user_id();
                if ($user_id === 0) {
                    return $compare !== '==';
                }

                $post_id = get_the_ID();
                if (!$post_id) {
                    return $compare !== '==';
                }

                $favorites = Favorites::get($user_id);
                $is_fav = false;
                foreach ($favorites as $fav) {
                    if ($fav['postId'] === $post_id) {
                        $is_fav = true;
                        break;
                    }
                }

                return $compare === '==' ? $is_fav : !$is_fav;

            case 'wpef_user_has_favorites':
                $user_id = get_current_user_id();
                if ($user_id === 0) {
                    return $compare !== '==';
                }

                $has = Favorites::user_count($user_id) > 0;

                return $compare === '==' ? $has : !$has;
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Parse the parameter after a tag prefix.
     *
     * E.g. "wpef_user_count:product" with prefix "wpef_user_count" returns "product".
     *
     * @param string $clean  Tag without braces.
     * @param string $prefix Tag prefix.
     * @return string Parameter value or empty string.
     */
    private static function parse_param(string $clean, string $prefix): string {
        if (strpos($clean, $prefix . ':') === 0) {
            return sanitize_key(substr($clean, strlen($prefix) + 1));
        }

        return '';
    }

    /**
     * Get the current post ID from the post object or global context.
     *
     * @param mixed $post Post object from Bricks.
     * @return int Post ID.
     */
    private static function get_current_post_id($post): int {
        if (is_object($post) && isset($post->ID)) {
            return (int) $post->ID;
        }

        return (int) get_the_ID();
    }
}
