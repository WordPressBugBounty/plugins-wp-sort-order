<?php
/**
 * Fired when the plugin is deleted from the Plugins screen.
 *
 * @package WPSortOrder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the columns and options added by WP Sort Order for the current site.
 */
function wpso_uninstall_db() {
	global $wpdb;

	if ( $wpdb->query( "DESCRIBE {$wpdb->terms} `term_order`" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "ALTER TABLE {$wpdb->terms} DROP `term_order`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	if ( $wpdb->query( "DESCRIBE {$wpdb->users} `user_order`" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "ALTER TABLE {$wpdb->users} DROP `user_order`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	delete_option( 'wpso_activation' );
	delete_option( 'wpso_options' );
	delete_option( 'wpso_extras_order' );
	delete_option( 'wpso_pro_options' );
	delete_option( 'wpso_pro_history' );
}

if ( is_multisite() ) {
	$wpso_blog_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wpso_blog_ids as $wpso_blog_id ) {
		switch_to_blog( $wpso_blog_id );
		wpso_uninstall_db();
		restore_current_blog();
	}
} else {
	wpso_uninstall_db();
}
