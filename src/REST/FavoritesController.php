<?php
/**
 * REST API controller for favorites.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\REST;

use WPE\Favorites\Favorites;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class FavoritesController {

    private const NAMESPACE = 'wpef/v1';
    private const ROUTE     = '/favorites';

    /**
     * Register REST routes.
     */
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register all favorites routes.
     */
    public static function register_routes(): void {
        // GET — list favorites.
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_favorites'],
            'permission_callback' => [self::class, 'check_permission'],
        ]);

        // POST — add a favorite.
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'add_favorite'],
            'permission_callback' => [self::class, 'check_permission'],
            'args'                => self::get_add_args(),
        ]);

        // PUT — bulk sync.
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => 'PUT',
            'callback'            => [self::class, 'sync_favorites'],
            'permission_callback' => [self::class, 'check_permission'],
            'args'                => [
                'favorites' => [
                    'required'          => true,
                    'type'              => 'array',
                    'sanitize_callback' => [self::class, 'sanitize_favorites_array'],
                ],
            ],
        ]);

        // GET — counts (public, no auth required).
        register_rest_route(self::NAMESPACE, '/counts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_counts'],
            'permission_callback' => '__return_true',
            'args'                => [
                'post_ids' => [
                    'type'              => 'array',
                    'default'           => [],
                    'sanitize_callback' => fn($val): array => array_map('absint', (array) $val),
                ],
                'post_types' => [
                    'type'              => 'array',
                    'default'           => [],
                    'sanitize_callback' => fn($val): array => array_map('sanitize_key', (array) $val),
                ],
            ],
        ]);

        // DELETE — clear all favorites (optionally by post type).
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [self::class, 'clear_favorites'],
            'permission_callback' => [self::class, 'check_permission'],
            'args'                => [
                'post_types' => [
                    'type'              => 'array',
                    'default'           => [],
                    'sanitize_callback' => fn($val): array => array_map('sanitize_key', (array) $val),
                ],
            ],
        ]);

        // DELETE — remove a single favorite.
        register_rest_route(self::NAMESPACE, self::ROUTE . '/(?P<postId>[\d]+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [self::class, 'remove_favorite'],
            'permission_callback' => [self::class, 'check_permission'],
            'args'                => [
                'postId' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'validate_callback' => fn($val): bool => is_numeric($val) && (int) $val > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Permission check — user must be logged in.
     */
    public static function check_permission(): bool {
        return current_user_can('read');
    }

    /**
     * GET /wpef/v1/favorites
     */
    public static function get_favorites(): WP_REST_Response {
        $favorites = Favorites::get(get_current_user_id());
        return new WP_REST_Response(['favorites' => $favorites], 200);
    }

    /**
     * POST /wpef/v1/favorites
     */
    public static function add_favorite(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id   = (int) $request->get_param('postId');
        $post_type = sanitize_key($request->get_param('postType'));

        if (!get_post($post_id)) {
            return new WP_Error('wpef_invalid_post', __('Post not found.', 'wpef'), ['status' => 404]);
        }

        $favorites = Favorites::add(get_current_user_id(), $post_id, $post_type);
        return new WP_REST_Response(['favorites' => $favorites], 200);
    }

    /**
     * DELETE /wpef/v1/favorites/{postId}
     */
    public static function remove_favorite(WP_REST_Request $request): WP_REST_Response {
        $post_id   = (int) $request->get_param('postId');
        $favorites = Favorites::remove(get_current_user_id(), $post_id);
        return new WP_REST_Response(['favorites' => $favorites], 200);
    }

    /**
     * DELETE /wpef/v1/favorites (no postId — clear all/filtered)
     */
    public static function clear_favorites(WP_REST_Request $request): WP_REST_Response {
        $post_types = $request->get_param('post_types');
        $favorites  = Favorites::clear(get_current_user_id(), is_array($post_types) ? $post_types : []);
        return new WP_REST_Response(['favorites' => $favorites], 200);
    }

    /**
     * PUT /wpef/v1/favorites
     */
    public static function sync_favorites(WP_REST_Request $request): WP_REST_Response {
        $raw       = $request->get_param('favorites');
        $favorites = Favorites::sync(get_current_user_id(), is_array($raw) ? $raw : []);
        return new WP_REST_Response(['favorites' => $favorites], 200);
    }

    /**
     * GET /wpef/v1/counts
     *
     * Returns post counts and global counts. Public endpoint.
     * Query params: post_ids[]=42&post_types[]=post
     */
    public static function get_counts(WP_REST_Request $request): WP_REST_Response {
        $post_ids   = $request->get_param('post_ids');
        $post_types = $request->get_param('post_types');

        $data = [
            'global' => Favorites::global_count(),
        ];

        // Per-post counts (capped at 500 per request to prevent abuse).
        if (!empty($post_ids)) {
            $post_counts = [];
            foreach (array_slice($post_ids, 0, 500) as $pid) {
                if ($pid > 0) {
                    $post_counts[$pid] = Favorites::get_post_count($pid);
                }
            }
            $data['posts'] = $post_counts;
        }

        // Per-type global counts (capped to prevent abuse).
        if (!empty($post_types)) {
            $type_counts = [];
            foreach (array_slice($post_types, 0, 50) as $pt) {
                if ($pt !== '') {
                    $type_counts[$pt] = Favorites::global_count_by_type($pt);
                }
            }
            $data['types'] = $type_counts;
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Sanitize the favorites array from the PUT sync endpoint.
     *
     * Each item must have postId (int > 0) and postType (string).
     * Invalid items are silently dropped.
     *
     * @param mixed $value Raw input.
     * @return array<int, array{postId: int, postType: string}>
     */
    public static function sanitize_favorites_array(mixed $value): array {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $post_id   = isset($item['postId']) ? absint($item['postId']) : 0;
            $post_type = isset($item['postType']) ? sanitize_key($item['postType']) : '';

            if ($post_id > 0 && $post_type !== '') {
                $clean[] = [
                    'postId'   => $post_id,
                    'postType' => $post_type,
                ];
            }
        }

        return $clean;
    }

    /**
     * Argument schema for POST (add).
     *
     * @return array<string, array>
     */
    private static function get_add_args(): array {
        return [
            'postId' => [
                'required'          => true,
                'type'              => 'integer',
                'validate_callback' => fn($val): bool => is_numeric($val) && (int) $val > 0,
            ],
            'postType' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
            ],
        ];
    }
}
