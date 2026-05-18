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

// Remove legacy FluentCart license option (and its multisite counterpart).
delete_option('wpef_license_settings');
if (is_multisite()) {
    delete_site_option('wpef_license_settings');
}
