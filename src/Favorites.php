<?php
/**
 * Core favorites data layer.
 *
 * Handles reading/writing favorites to user meta and post meta counts.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites;

use WP_Error;

defined('ABSPATH') || exit;

final class Favorites {

    private const META_KEY_BASE   = 'wpef_favorites';
    private const POST_COUNT_KEY  = 'wpef_count';

    /* ------------------------------------------------------------------ */
    /*  Meta key (site-scoped on multisite)                                */
    /* ------------------------------------------------------------------ */

    /**
     * Get the user meta key for favorites.
     *
     * On multisite, the key is suffixed with the blog ID to prevent
     * cross-site data contamination (usermeta is a shared table).
     *
     * @return string
     */
    private static function meta_key(): string {
        if (is_multisite()) {
            return self::META_KEY_BASE . '_' . get_current_blog_id();
        }

        return self::META_KEY_BASE;
    }

    /* ------------------------------------------------------------------ */
    /*  Per-user CRUD                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Get all favorites for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array<int, array{postId: int, postType: string}>
     */
    public static function get(int $user_id): array {
        $favorites = get_user_meta($user_id, self::meta_key(), true);

        if (!is_array($favorites)) {
            return [];
        }

        return self::sanitize_array($favorites);
    }

    /**
     * Add a favorite.
     *
     * @param int    $user_id   WordPress user ID.
     * @param int    $post_id   Post ID to favorite.
     * @param string $post_type Post type slug.
     * @return array<int, array{postId: int, postType: string}>|WP_Error Updated favorites or error if limit exceeded.
     */
    public static function add(int $user_id, int $post_id, string $post_type): array|WP_Error {
        $favorites = self::get($user_id);

        // Don't add duplicates.
        foreach ($favorites as $fav) {
            if ($fav['postId'] === $post_id) {
                return $favorites;
            }
        }

        // Check per-type limit.
        $type_limit = Admin\Settings::get_limit_for_type($post_type);
        if ($type_limit > 0) {
            $type_count = count(array_filter(
                $favorites,
                fn(array $f): bool => $f['postType'] === $post_type
            ));
            if ($type_count >= $type_limit) {
                $type_obj  = get_post_type_object($post_type);
                $type_name = $type_obj ? $type_obj->labels->name : $post_type;
                return new WP_Error(
                    'wpef_type_limit',
                    /* translators: 1: post type name, 2: limit number */
                    sprintf(__('You can only favorite up to %2$d %1$s.', 'wpef'), $type_name, $type_limit),
                    ['status' => 400]
                );
            }
        }

        // Check global limit.
        $max = Admin\Settings::get_max_favorites();
        if ($max > 0 && count($favorites) >= $max) {
            return new WP_Error(
                'wpef_global_limit',
                /* translators: %d: max favorites number */
                sprintf(__('You can only have up to %d total favorites.', 'wpef'), $max),
                ['status' => 400]
            );
        }

        $favorites[] = [
            'postId'   => $post_id,
            'postType' => $post_type,
        ];

        update_user_meta($user_id, self::meta_key(), $favorites);
        self::increment_post_count($post_id);

        return $favorites;
    }

    /**
     * Remove a favorite by post ID.
     *
     * @param int $user_id WordPress user ID.
     * @param int $post_id Post ID to remove.
     * @return array<int, array{postId: int, postType: string}> Updated favorites.
     */
    public static function remove(int $user_id, int $post_id): array {
        $favorites = self::get($user_id);
        $original_count = count($favorites);

        $favorites = array_values(array_filter(
            $favorites,
            fn(array $fav): bool => $fav['postId'] !== $post_id
        ));

        update_user_meta($user_id, self::meta_key(), $favorites);

        if (count($favorites) < $original_count) {
            self::decrement_post_count($post_id);
        }

        return $favorites;
    }

    /**
     * Bulk sync — replace all favorites (used for login merge).
     *
     * @param int   $user_id   WordPress user ID.
     * @param array $favorites Full favorites array.
     * @return array<int, array{postId: int, postType: string}> Sanitized favorites.
     */
    public static function sync(int $user_id, array $favorites): array {
        $old   = self::get($user_id);
        $clean = self::sanitize_array($favorites);
        $clean = self::deduplicate($clean);
        $clean = self::enforce_limits($clean);

        update_user_meta($user_id, self::meta_key(), $clean);

        // Update post counts for the diff.
        $old_ids = array_column($old, 'postId');
        $new_ids = array_column($clean, 'postId');

        $added   = array_diff($new_ids, $old_ids);
        $removed = array_diff($old_ids, $new_ids);

        foreach ($added as $pid) {
            self::increment_post_count($pid);
        }
        foreach ($removed as $pid) {
            self::decrement_post_count($pid);
        }

        return $clean;
    }

    /**
     * Clear all favorites for a user, optionally filtered by post types.
     *
     * @param int      $user_id    WordPress user ID.
     * @param string[] $post_types Optional post types to clear. Empty = clear all.
     * @return array<int, array{postId: int, postType: string}> Remaining favorites.
     */
    public static function clear(int $user_id, array $post_types = []): array {
        $favorites = self::get($user_id);

        if (empty($favorites)) {
            return [];
        }

        $meta_key = self::meta_key();

        if (empty($post_types)) {
            // Clear everything — decrement all post counts.
            foreach ($favorites as $fav) {
                self::decrement_post_count($fav['postId']);
            }
            update_user_meta($user_id, $meta_key, []);
            return [];
        }

        // Selective clear — remove only matching post types.
        $remaining = [];
        foreach ($favorites as $fav) {
            if (in_array($fav['postType'], $post_types, true)) {
                self::decrement_post_count($fav['postId']);
            } else {
                $remaining[] = $fav;
            }
        }

        update_user_meta($user_id, $meta_key, $remaining);
        return $remaining;
    }

    /**
     * Remove a post ID from ALL users' favorites.
     *
     * Used when a post is permanently deleted. On multisite, scoped
     * to users of the current site only.
     *
     * @param int $post_id Post ID to purge.
     */
    public static function purge_post(int $post_id): void {
        $meta_key = self::meta_key();
        $user_ids = self::get_site_user_ids_with_favorites();

        foreach ($user_ids as $uid) {
            $favorites = get_user_meta($uid, $meta_key, true);

            if (!is_array($favorites)) {
                continue;
            }

            $filtered = array_values(array_filter(
                $favorites,
                fn(array $fav): bool => (int) ($fav['postId'] ?? 0) !== $post_id
            ));

            if (count($filtered) !== count($favorites)) {
                update_user_meta($uid, $meta_key, $filtered);
            }
        }

        // Clean up the post meta count.
        delete_post_meta($post_id, self::POST_COUNT_KEY);
    }

    /* ------------------------------------------------------------------ */
    /*  Per-user counts                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Count total favorites for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return int
     */
    public static function user_count(int $user_id): int {
        return count(self::get($user_id));
    }

    /**
     * Count favorites for a user filtered by post type.
     *
     * @param int    $user_id   WordPress user ID.
     * @param string $post_type Post type slug.
     * @return int
     */
    public static function user_count_by_type(int $user_id, string $post_type): int {
        $favorites = self::get($user_id);

        return count(array_filter(
            $favorites,
            fn(array $fav): bool => $fav['postType'] === $post_type
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  Global post counts (stored in post meta)                           */
    /* ------------------------------------------------------------------ */

    /**
     * Get the total number of users who favorited a post.
     *
     * @param int $post_id Post ID.
     * @return int
     */
    public static function get_post_count(int $post_id): int {
        return absint(get_post_meta($post_id, self::POST_COUNT_KEY, true));
    }

    /**
     * Increment the favorite count for a post.
     *
     * @param int $post_id Post ID.
     */
    private static function increment_post_count(int $post_id): void {
        $count = self::get_post_count($post_id);
        update_post_meta($post_id, self::POST_COUNT_KEY, $count + 1);
    }

    /**
     * Decrement the favorite count for a post (minimum 0).
     *
     * @param int $post_id Post ID.
     */
    private static function decrement_post_count(int $post_id): void {
        $count = self::get_post_count($post_id);
        update_post_meta($post_id, self::POST_COUNT_KEY, max(0, $count - 1));
    }

    /**
     * Recalculate the favorite count for a post by scanning current-site users.
     *
     * Use sparingly — expensive query. Useful for data repair.
     *
     * @param int $post_id Post ID.
     * @return int Recalculated count.
     */
    public static function recalculate_post_count(int $post_id): int {
        $meta_key = self::meta_key();
        $user_ids = self::get_site_user_ids_with_favorites();

        $count = 0;
        foreach ($user_ids as $uid) {
            $favorites = get_user_meta($uid, $meta_key, true);

            if (!is_array($favorites)) {
                continue;
            }

            foreach ($favorites as $fav) {
                if (is_array($fav) && (int) ($fav['postId'] ?? 0) === $post_id) {
                    $count++;
                    break;
                }
            }
        }

        update_post_meta($post_id, self::POST_COUNT_KEY, $count);

        return $count;
    }

    /* ------------------------------------------------------------------ */
    /*  Global aggregate counts                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Get the total number of favorites across all users on the current site.
     *
     * @return int
     */
    public static function global_count(): int {
        $meta_key = self::meta_key();
        $user_ids = self::get_site_user_ids_with_favorites();

        $total = 0;
        foreach ($user_ids as $uid) {
            $data = get_user_meta($uid, $meta_key, true);
            if (is_array($data)) {
                $total += count($data);
            }
        }

        return $total;
    }

    /**
     * Get the total number of favorites across all users for a specific post type.
     *
     * @param string $post_type Post type slug.
     * @return int
     */
    public static function global_count_by_type(string $post_type): int {
        $meta_key = self::meta_key();
        $user_ids = self::get_site_user_ids_with_favorites();

        $total = 0;
        foreach ($user_ids as $uid) {
            $data = get_user_meta($uid, $meta_key, true);
            if (is_array($data)) {
                foreach ($data as $fav) {
                    if (is_array($fav) && ($fav['postType'] ?? '') === $post_type) {
                        $total++;
                    }
                }
            }
        }

        return $total;
    }

    /* ------------------------------------------------------------------ */
    /*  Internal helpers                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Get user IDs on the current site that have favorites meta.
     *
     * On multisite, scoped to users of the current blog via get_users().
     * On single site, falls back to a direct usermeta query for performance.
     *
     * @return int[]
     */
    private static function get_site_user_ids_with_favorites(): array {
        $meta_key = self::meta_key();

        if (is_multisite()) {
            $users = get_users([
                'blog_id'  => get_current_blog_id(),
                'fields'   => 'ID',
                'meta_key' => $meta_key,
            ]);

            return array_map('intval', $users);
        }

        // Single site — direct query is fine.
        global $wpdb;

        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            )
        );

        return array_map('intval', $user_ids);
    }

    /**
     * Sanitize a favorites array.
     *
     * @param array $raw Raw input.
     * @return array<int, array{postId: int, postType: string}>
     */
    private static function sanitize_array(array $raw): array {
        $clean = [];

        foreach ($raw as $item) {
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
     * Remove duplicate entries by postId.
     *
     * @param array $favorites Sanitized favorites.
     * @return array<int, array{postId: int, postType: string}>
     */
    private static function deduplicate(array $favorites): array {
        $seen   = [];
        $result = [];

        foreach ($favorites as $fav) {
            if (!in_array($fav['postId'], $seen, true)) {
                $seen[]   = $fav['postId'];
                $result[] = $fav;
            }
        }

        return $result;
    }

    /**
     * Enforce per-type and global limits on a favorites array.
     *
     * Keeps the first N items when truncating (preserves order).
     *
     * @param array $favorites Sanitized, deduplicated favorites.
     * @return array<int, array{postId: int, postType: string}>
     */
    private static function enforce_limits(array $favorites): array {
        // Per-type limits: group by type, truncate each group.
        $settings = Admin\Settings::get_settings();
        $limits   = $settings['limits_per_type'];

        if (!empty($limits)) {
            $counts = [];
            $result = [];

            foreach ($favorites as $fav) {
                $pt = $fav['postType'];
                $counts[$pt] = ($counts[$pt] ?? 0) + 1;

                if (isset($limits[$pt]) && $counts[$pt] > $limits[$pt]) {
                    continue; // Skip — over per-type limit.
                }

                $result[] = $fav;
            }

            $favorites = $result;
        }

        // Global limit.
        $max = (int) ($settings['max_favorites'] ?? 0);
        if ($max > 0 && count($favorites) > $max) {
            $favorites = array_slice($favorites, 0, $max);
        }

        return $favorites;
    }
}
