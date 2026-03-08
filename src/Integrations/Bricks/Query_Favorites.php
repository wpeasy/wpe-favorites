<?php
/**
 * Bricks custom query: Favorites.
 *
 * Loops through the current user's favorited posts with post type filtering,
 * ordering, and pagination support.
 *
 * @package WPE\Favorites
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Integrations\Bricks;

use WPE\Favorites\Favorites;
use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

final class Query_Favorites {

    private const QUERY_TYPE = 'wpef_favorites';

    /**
     * Nestable elements that support query loops.
     *
     * @var string[]
     */
    private const LOOP_ELEMENTS = ['container', 'block', 'div'];

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_filter('bricks/setup/control_options', [self::class, 'add_query_type']);
        add_filter('bricks/query/run', [self::class, 'run'], 10, 2);
        add_filter('bricks/query/loop_object', [self::class, 'set_post_data'], 10, 3);
        add_filter('bricks/query/loop_object_id', [self::class, 'get_object_id'], 10, 3);
        add_filter('bricks/query/loop_object_type', [self::class, 'get_object_type'], 10, 3);

        foreach (self::LOOP_ELEMENTS as $element) {
            add_filter("bricks/elements/{$element}/controls", [self::class, 'add_controls']);
        }
    }

    /**
     * Add the Favorites query type to the Bricks query type dropdown.
     *
     * @param array<string, mixed> $options Control options.
     * @return array<string, mixed>
     */
    public static function add_query_type(array $options): array {
        $options['queryTypes'][self::QUERY_TYPE] = esc_html__('Favorites', 'wpef');
        return $options;
    }

    /**
     * Add query controls for the Favorites query type.
     *
     * @param array<string, mixed> $controls Element controls.
     * @return array<string, mixed>
     */
    public static function add_controls(array $controls): array {
        $required = [['query.objectType', '=', self::QUERY_TYPE]];

        $post_type_options = self::get_post_type_options();

        $controls['wpefFavoritesInfo'] = [
            'group'       => 'query',
            'label'       => esc_html__('Info', 'wpef'),
            'type'        => 'info',
            'content'     => esc_html__('Loops through the current logged-in user\'s favorited posts. Anonymous visitors see no results.', 'wpef'),
            'required'    => $required,
        ];

        $controls['wpefFavoritesPostType'] = [
            'group'       => 'query',
            'label'       => esc_html__('Post Type', 'wpef'),
            'type'        => 'select',
            'options'     => $post_type_options,
            'default'     => '',
            'placeholder' => esc_html__('All', 'wpef'),
            'required'    => $required,
        ];

        $controls['wpefFavoritesPerPage'] = [
            'group'       => 'query',
            'label'       => esc_html__('Posts Per Page', 'wpef'),
            'type'        => 'number',
            'default'     => -1,
            'placeholder' => '-1',
            'description' => esc_html__('-1 for all results.', 'wpef'),
            'required'    => $required,
        ];

        $controls['wpefFavoritesOrderby'] = [
            'group'       => 'query',
            'label'       => esc_html__('Order By', 'wpef'),
            'type'        => 'select',
            'options'     => [
                'title'    => esc_html__('Post Title', 'wpef'),
                'date'     => esc_html__('Publish Date', 'wpef'),
                'modified' => esc_html__('Modified Date', 'wpef'),
                'ID'       => esc_html__('Post ID', 'wpef'),
                'rand'     => esc_html__('Random', 'wpef'),
                'post__in' => esc_html__('Favorited Order', 'wpef'),
            ],
            'default'     => 'title',
            'required'    => $required,
        ];

        $controls['wpefFavoritesOrder'] = [
            'group'       => 'query',
            'label'       => esc_html__('Order', 'wpef'),
            'type'        => 'select',
            'options'     => [
                'ASC'  => esc_html__('Ascending', 'wpef'),
                'DESC' => esc_html__('Descending', 'wpef'),
            ],
            'default'     => 'ASC',
            'required'    => $required,
        ];

        return $controls;
    }

    /**
     * Execute the Favorites query.
     *
     * @param array  $results   Default empty results.
     * @param object $query_obj Bricks Query object.
     * @return array
     */
    public static function run(mixed $results, mixed $query_obj): mixed {
        if (!is_object($query_obj)) {
            return $results;
        }
        if ($query_obj->object_type !== self::QUERY_TYPE) {
            return $results;
        }

        $user_id = get_current_user_id();

        if ($user_id === 0) {
            $query_obj->count         = 0;
            $query_obj->max_num_pages = 0;
            return [];
        }

        $favorites = Favorites::get($user_id);

        if (empty($favorites)) {
            $query_obj->count         = 0;
            $query_obj->max_num_pages = 0;
            return [];
        }

        $settings  = $query_obj->settings ?? [];
        $post_type = sanitize_key($settings['wpefFavoritesPostType'] ?? '');

        // Filter favorites by post type if specified.
        if ($post_type !== '') {
            $favorites = array_filter(
                $favorites,
                fn(array $fav): bool => $fav['postType'] === $post_type
            );
        }

        $post_ids = array_column($favorites, 'postId');

        if (empty($post_ids)) {
            $query_obj->count         = 0;
            $query_obj->max_num_pages = 0;
            return [];
        }

        $per_page = (int) ($settings['wpefFavoritesPerPage'] ?? -1);
        $orderby  = sanitize_key($settings['wpefFavoritesOrderby'] ?? 'title');
        $order    = strtoupper($settings['wpefFavoritesOrder'] ?? 'ASC');

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = ['title', 'date', 'modified', 'ID', 'rand', 'post__in'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'title';
        }

        // Determine current page.
        $paged = max(1, absint($query_obj->page ?? get_query_var('paged', 1)));

        // Determine post types to query.
        $query_post_types = $post_type !== '' ? [$post_type] : array_unique(array_column($favorites, 'postType'));

        $wp_query = new \WP_Query([
            'post_type'      => $query_post_types,
            'post__in'       => $post_ids,
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => $orderby,
            'order'          => $order,
            'post_status'    => 'publish',
        ]);

        // Set pagination properties for Bricks.
        $query_obj->count         = $wp_query->found_posts;
        $query_obj->max_num_pages = $wp_query->max_num_pages;
        $query_obj->query_result  = $wp_query;

        return $wp_query->posts;
    }

    /**
     * Set up WordPress post data for each loop iteration.
     *
     * @param mixed  $loop_object Current loop object.
     * @param int    $loop_key    Current iteration key.
     * @param object $query_obj   Bricks Query object.
     * @return mixed
     */
    public static function set_post_data(mixed $loop_object, mixed $loop_key, mixed $query_obj): mixed {
        if (!is_object($query_obj) || ($query_obj->object_type ?? '') !== self::QUERY_TYPE) {
            return $loop_object;
        }

        if ($loop_object instanceof \WP_Post) {
            global $post;
            $post = $loop_object;
            setup_postdata($post);
        }

        return $loop_object;
    }

    /**
     * Return the correct object ID for each loop iteration.
     *
     * @param mixed $object_id Default object ID.
     * @param mixed $object    Current loop object.
     * @param mixed $query_id  Query identifier.
     * @return mixed
     */
    public static function get_object_id(mixed $object_id, mixed $object, mixed $query_id): mixed {
        if (!$query_id) {
            return $object_id;
        }

        $query_object_type = \Bricks\Query::get_query_object_type($query_id);

        if ($query_object_type !== self::QUERY_TYPE) {
            return $object_id;
        }

        if ($object instanceof \WP_Post) {
            return $object->ID;
        }

        return $object_id;
    }

    /**
     * Tell Bricks to treat loop objects as posts for dynamic data.
     *
     * @param mixed $object_type Current object type.
     * @param mixed $object      Current loop object.
     * @param mixed $query_id    Query identifier.
     * @return mixed
     */
    public static function get_object_type(mixed $object_type, mixed $object, mixed $query_id): mixed {
        if (!$query_id) {
            return $object_type;
        }

        $query_object_type = \Bricks\Query::get_query_object_type($query_id);

        if ($query_object_type !== self::QUERY_TYPE) {
            return $object_type;
        }

        return 'post';
    }

    /**
     * Build post type options for the control dropdown.
     *
     * @return array<string, string>
     */
    private static function get_post_type_options(): array {
        $types   = Plugin::get_supported_post_types();
        $options = [];

        foreach ($types as $type) {
            $obj = get_post_type_object($type);
            if ($obj) {
                $options[$type] = $obj->labels->name;
            }
        }

        return $options;
    }
}
