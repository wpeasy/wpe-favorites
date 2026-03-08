<?php
/**
 * Uninstall handler.
 *
 * Cleans up all plugin data when uninstalled via wp-admin.
 *
 * @package WPE\Favorites
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Remove all user meta.
$wpdb->delete($wpdb->usermeta, ['meta_key' => 'wpef_favorites']);
