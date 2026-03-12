<?php
/**
 * Plugin Name: WPE Favorites
 * Plugin URI:
 * Description: User favorites system for WordPress Posts and Custom Post Types.
 * Version: 1.0.8
 * Author: Alan Blair
 * Author URI:
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpef
 * Requires at least: 6.5
 * Requires PHP: 8.0
 *
 * @package WPE\Favorites
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('WPEF_VERSION', '1.0.8');
define('WPEF_PLUGIN_FILE', __FILE__);
define('WPEF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPEF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Licensing Configuration.
define('WPEF_LICENSE_ITEM_ID', 1016);
define('WPEF_LICENSE_API_URL', 'https://alanblair.co/');
define('WPEF_LICENSE_SLUG', 'wpe-favorites');
define('WPEF_LICENSE_SETTINGS_KEY', 'wpef_license_settings');

require_once WPEF_PLUGIN_DIR . 'vendor/autoload.php';

WPE\Favorites\Plugin::init();
