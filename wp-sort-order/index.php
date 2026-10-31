<?php
/**
 * WP Sort Order
 *
 * @package           WPSortOrder
 * @author            Fahad Mahmood
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP Sort Order
 * Plugin URI:        https://androidbubble.com/blog/wordpress/plugins/wp-sort-order
 * Description:       Order plugins, terms (Users, Posts, Pages, Custom Post Types and Custom Taxonomies) using Drag and Drop with jQuery UI Sortable.
 * Version:           1.3.7
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            Fahad Mahmood
 * Author URI:        https://www.androidbubble.com
 * Text Domain:       wpso-sort-order
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPSO_VERSION' ) ) {
	define( 'WPSO_VERSION', '1.3.7' );
}
if ( ! defined( 'WPSO_FILE' ) ) {
	define( 'WPSO_FILE', __FILE__ );
}
if ( ! defined( 'WPSO_URL' ) ) {
	define( 'WPSO_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
}
if ( ! defined( 'WPSO_DIR' ) ) {
	define( 'WPSO_DIR', plugin_dir_path( __FILE__ ) );
}

global $wpso_data, $wpso_pro, $wpso_premium_link, $wpso_premium, $premium_click, $wpso_allowed_pages, $premium_tags_list;

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

require_once WPSO_DIR . 'inc/functions.php';

$wpso_allowed_pages = array( 'plugins.php' => 'Plugins' );
$wpso_data          = get_plugin_data( __FILE__, true, false );
$premium_tags_list  = array( 'usercategories' );

/*
 * PRO "plug and play" switch.
 *
 * If the /pro directory ships with a loader (pro/wpso_pro.php) or the legacy
 * pro/wpso_extended.php, premium features are enabled. If the directory is
 * missing, the free version simply keeps working - nothing is ever required
 * from /pro by the free code.
 */
$wpso_premium      = WPSO_DIR . 'pro/wpso_pro.php';
$wpso_premium_old  = WPSO_DIR . 'pro/wpso_extended.php';
$wpso_pro          = ( is_readable( $wpso_premium ) || is_readable( $wpso_premium_old ) );
$wpso_premium_link = 'https://shop.androidbubbles.com/product/wp-sort-order-pro';

// Built lazily in settings.php (translations must not be called before "init").
$premium_click = '';

if ( is_admin() ) {
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpso_plugin_links' );
}

/**
 * Load translations.
 */
function wpso_textdomain() {
	load_plugin_textdomain( 'wpso-sort-order', false, dirname( plugin_basename( WPSO_FILE ) ) . '/languages' );
}
add_action( 'init', 'wpso_textdomain' );

function wpso_activate() {
	// Schema changes are handled lazily by Wpso::wpso_activation().
}
register_activation_hook( __FILE__, 'wpso_activate' );

require_once WPSO_DIR . 'inc/hooks.php';

/*
 * Pro loader - last, so it can use every free hook. Wrapped so that a broken or
 * partial /pro folder can never take the free plugin (or the site) down.
 */
if ( $wpso_pro ) {
	if ( is_readable( $wpso_premium ) ) {
		require_once $wpso_premium;
	} elseif ( is_readable( $wpso_premium_old ) ) {
		require_once $wpso_premium_old;
	}
	/**
	 * Fires once the pro folder has been loaded.
	 */
	do_action( 'wpso_pro_loaded' );
}
