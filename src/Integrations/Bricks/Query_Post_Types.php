<?php
/**
 * Bricks custom query: Favorite Post Types.
 *
 * Loops through the post types that have favorites enabled,
 * exposing post type data for each iteration.
 *
 * @package WPE\Favorites
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Integrations\Bricks;

use WPE\Favorites\Plugin;

defined('ABSPATH') || exit;

final class Query_Post_Types {

    private const QUERY_TYPE = 'wpef_post_types';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_filter('bricks/setup/control_options', [self::class, 'add_query_type']);
        add_filter('bricks/query/run', [self::class, 'run'], 10, 2);
        add_filter('bricks/query/loop_object', [self::class, 'setup_object'], 10, 3);
        add_filter('bricks/query/loop_object_id', [self::class, 'get_object_id'], 10, 3);
        add_filter('bricks/query/loop_object_type', [self::class, 'get_object_type'], 10, 3);
    }

    /**
     * Add the Post Types query type to the Bricks dropdown.
     *
     * @param array<string, mixed> $options Control options.
     * @return array<string, mixed>
     */
    public static function add_query_type(array $options): array {
        $options['queryTypes'][self::QUERY_TYPE] = esc_html__('Favorite Post Types', 'wpef');
        return $options;
    }

    /**
     * Execute the Post Types query.
     *
     * Returns an array of objects with post type properties accessible
     * via Bricks dynamic data: {wpef_name}, {wpef_label}, {wpef_singular},
     * {wpef_slug}, {wpef_archive_url}.
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

        $types   = Plugin::get_supported_post_types();
        $objects = [];

        foreach ($types as $type_name) {
            $type_obj = get_post_type_object($type_name);

            if (!$type_obj) {
                continue;
            }

            $objects[] = (object) [
                'id'           => $type_name,
                'name'         => $type_name,
                'label'        => $type_obj->labels->name,
                'singular'     => $type_obj->labels->singular_name,
                'slug'         => $type_obj->rewrite['slug'] ?? $type_name,
                'archive_url'  => get_post_type_archive_link($type_name) ?: '',
                'description'  => $type_obj->description,
                'icon'         => $type_obj->menu_icon ?? '',
            ];
        }

        $query_obj->count         = count($objects);
        $query_obj->max_num_pages = 1;

        return $objects;
    }

    /**
     * Make post type properties available for the current loop iteration.
     *
     * @param mixed  $loop_object Current loop object.
     * @param int    $loop_key    Current iteration key.
     * @param object $query_obj   Bricks Query object.
     * @return mixed
     */
    public static function setup_object(mixed $loop_object, mixed $loop_key, mixed $query_obj): mixed {
        if (!is_object($query_obj) || ($query_obj->object_type ?? '') !== self::QUERY_TYPE) {
            return $loop_object;
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

        if (is_object($object) && isset($object->id)) {
            return $object->id;
        }

        return $object_id;
    }

    /**
     * Return the object type for Bricks dynamic data resolution.
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

        return self::QUERY_TYPE;
    }
}
