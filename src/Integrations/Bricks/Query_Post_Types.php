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
        add_filter('bricks/query/loop_object_type', [self::class, 'get_object_type'], 10, 3);

        foreach (self::LOOP_ELEMENTS as $element) {
            add_filter("bricks/elements/{$element}/controls", [self::class, 'add_controls']);
        }
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
     * Add query controls for the Post Types query type.
     *
     * Inserts controls after the 'query' key so they appear inside the
     * query section in the Bricks panel, not at the bottom.
     *
     * @param array<string, mixed> $controls Element controls.
     * @return array<string, mixed>
     */
    public static function add_controls(array $controls): array {
        $required = [['query.objectType', '=', self::QUERY_TYPE], ['hasLoop', '!=', false]];

        $post_type_options = self::get_post_type_options();

        $new_controls = [];

        $new_controls['wpefPostTypesInfo'] = [
            'tab'      => 'content',
            'label'    => esc_html__('Info', 'wpef'),
            'type'     => 'info',
            'content'  => esc_html__('Loops through post types with favorites enabled. Use {query_array @key:\'label\'} etc. for dynamic data.', 'wpef'),
            'required' => $required,
        ];

        $new_controls['wpefPostTypesFilter'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Post Types', 'wpef'),
            'type'        => 'select',
            'options'     => $post_type_options,
            'multiple'    => true,
            'default'     => [],
            'placeholder' => esc_html__('All', 'wpef'),
            'description' => esc_html__('Filter to specific post types, or leave empty for all.', 'wpef'),
            'required'    => $required,
        ];

        $new_controls['wpefPostTypesOrderby'] = [
            'tab'      => 'content',
            'label'    => esc_html__('Order By', 'wpef'),
            'type'     => 'select',
            'options'  => [
                'label' => esc_html__('Label', 'wpef'),
                'name'  => esc_html__('Slug', 'wpef'),
            ],
            'default'  => 'label',
            'required' => $required,
        ];

        $new_controls['wpefPostTypesOrder'] = [
            'tab'      => 'content',
            'label'    => esc_html__('Order', 'wpef'),
            'type'     => 'select',
            'options'  => [
                'ASC'  => esc_html__('Ascending', 'wpef'),
                'DESC' => esc_html__('Descending', 'wpef'),
            ],
            'default'  => 'ASC',
            'required' => $required,
        ];

        return self::insert_after_key($controls, 'query', $new_controls);
    }

    /**
     * Execute the Post Types query.
     *
     * Returns associative arrays so Bricks' built-in {query_array} tag works:
     *   {query_array @key:'name'}        — post type slug (e.g. "product")
     *   {query_array @key:'label'}       — plural label (e.g. "Products")
     *   {query_array @key:'singular'}    — singular label (e.g. "Product")
     *   {query_array @key:'slug'}        — rewrite slug
     *   {query_array @key:'archive_url'} — archive page URL
     *   {query_array @key:'description'} — post type description
     *   {query_array @key:'icon'}        — dashicon or SVG
     *
     * @param mixed $results   Default empty results.
     * @param mixed $query_obj Bricks Query object.
     * @return mixed
     */
    public static function run(mixed $results, mixed $query_obj): mixed {
        if (!is_object($query_obj)) {
            return $results;
        }
        if ($query_obj->object_type !== self::QUERY_TYPE) {
            return $results;
        }

        $settings = $query_obj->settings ?? [];
        $filter   = $settings['wpefPostTypesFilter'] ?? [];
        $orderby  = sanitize_key($settings['wpefPostTypesOrderby'] ?? 'label');
        $order    = strtoupper($settings['wpefPostTypesOrder'] ?? 'ASC');

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $types = Plugin::get_supported_post_types();

        // Filter to selected post types if any are specified.
        if (!empty($filter) && is_array($filter)) {
            $filter = array_map('sanitize_key', $filter);
            $types  = array_intersect($types, $filter);
        }

        $items = [];

        foreach ($types as $type_name) {
            $type_obj = get_post_type_object($type_name);

            if (!$type_obj) {
                continue;
            }

            $items[] = [
                'name'        => $type_name,
                'label'       => $type_obj->labels->name,
                'singular'    => $type_obj->labels->singular_name,
                'slug'        => $type_obj->rewrite['slug'] ?? $type_name,
                'archive_url' => get_post_type_archive_link($type_name) ?: '',
                'description' => $type_obj->description,
                'icon'        => $type_obj->menu_icon ?? '',
            ];
        }

        // Sort results.
        $sort_key = in_array($orderby, ['name', 'label'], true) ? $orderby : 'label';
        usort($items, function (array $a, array $b) use ($sort_key, $order): int {
            $cmp = strcasecmp($a[$sort_key], $b[$sort_key]);
            return $order === 'DESC' ? -$cmp : $cmp;
        });

        $query_obj->count         = count($items);
        $query_obj->max_num_pages = 1;

        return $items;
    }

    /**
     * Tell Bricks this is an array loop so {query_array @key:'...'} tags work.
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

        return 'array';
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

    /**
     * Insert new controls after a specific key in the controls array.
     *
     * This ensures controls appear in the correct position in the
     * Bricks panel rather than at the bottom.
     *
     * @param array<string, mixed> $controls     Existing controls.
     * @param string               $after_key    Key to insert after.
     * @param array<string, mixed> $new_controls Controls to insert.
     * @return array<string, mixed>
     */
    private static function insert_after_key(array $controls, string $after_key, array $new_controls): array {
        $position = array_search($after_key, array_keys($controls), true);

        if ($position === false) {
            return array_merge($controls, $new_controls);
        }

        $before = array_slice($controls, 0, $position + 1, true);
        $after  = array_slice($controls, $position + 1, null, true);

        return array_merge($before, $new_controls, $after);
    }
}
