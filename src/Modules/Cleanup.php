<?php
/**
 * Post deletion cleanup.
 *
 * Removes deleted posts from all users' favorites.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Modules;

use WPE\Favorites\Favorites;

defined('ABSPATH') || exit;

final class Cleanup {

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('before_delete_post', [self::class, 'on_post_delete']);
    }

    /**
     * Purge a post from all favorites when permanently deleted.
     *
     * @param int $post_id Post being deleted.
     */
    public static function on_post_delete(int $post_id): void {
        Favorites::purge_post($post_id);
    }
}
