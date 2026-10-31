<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the optional /pro add-on present and loaded?
 *
 * @return bool
 */
function wpso_is_pro() {
	return ! empty( $GLOBALS['wpso_pro'] );
}

/**
 * Recursively sanitize plugin data.
 *
 * @param mixed $input Raw value.
 * @return mixed
 */
function sanitize_wpso_data( $input ) {
	if ( is_array( $input ) ) {
		$new_input = array();
		foreach ( $input as $key => $val ) {
			$key               = is_int( $key ) ? $key : sanitize_text_field( (string) $key );
			$new_input[ $key ] = is_array( $val ) ? sanitize_wpso_data( $val ) : sanitize_text_field( (string) $val );
		}
		return $new_input;
	}

	$new_input = sanitize_text_field( (string) $input );
	if ( false !== stripos( $new_input, '@' ) && is_email( $new_input ) ) {
		$new_input = sanitize_email( $new_input );
	}
	if ( 0 === stripos( $new_input, 'http' ) && wp_http_validate_url( $new_input ) ) {
		$new_input = sanitize_url( $new_input );
	}
	return $new_input;
}

/**
 * Who may drag & drop?  Administrators by default; the pro add-on may extend this
 * (never to anything the user could not already edit - every handler also checks
 * per-object capabilities).
 *
 * @return bool
 */
function wpso_user_can_sort() {
	return (bool) apply_filters( 'wpso_user_can_sort', current_user_can( 'manage_options' ) );
}

/**
 * Read a sanitized value from $_GET.
 *
 * @param string $key     Query var.
 * @param string $default Default.
 * @return string
 */
function wpso_get( $key, $default = '' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing, no state change.
	return isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
}

/**
 * Current admin request path (sanitized).
 *
 * @return string
 */
function wpso_request_uri() {
	return isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
}

function wp_wpso_enqueue_scripts() {
	global $wpdb;

	wp_enqueue_script(
		'wpso-front-scripts',
		plugins_url( 'js/front-scripts.js', WPSO_FILE ),
		array( 'jquery' ),
		WPSO_VERSION,
		true
	);

	$wp_wpso_array = array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
	);

	$expected_items = array();

	// "User Categories" is a premium taxonomy - nothing to look up without it.
	if ( wpso_is_pro() && taxonomy_exists( 'usercategories' ) ) {
		$expected_terms = $wpdb->get_results(
			$wpdb->prepare( "SELECT t.term_id FROM {$wpdb->term_taxonomy} t WHERE t.taxonomy = %s", 'usercategories' )
		);

		foreach ( (array) $expected_terms as $eterms ) {
			$tax_term = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT t.taxonomy, t.term_id FROM {$wpdb->term_taxonomy} t, {$wpdb->terms} tr WHERE tr.term_id = %d AND tr.term_id = t.term_id LIMIT 1",
					$eterms->term_id
				)
			);
			if ( ! $tax_term ) {
				continue;
			}

			$taxonomy   = $tax_term->taxonomy;
			$term_id    = (int) $tax_term->term_id;
			$term_items = get_term_children( $term_id, $taxonomy );

			if ( empty( $term_items ) || is_wp_error( $term_items ) ) {
				$term_items_direct = get_objects_in_term( $term_id, $taxonomy );
				$term_items        = ( ! empty( $term_items_direct ) && ! is_wp_error( $term_items_direct ) ) ? array( $term_id ) : array();
			}

			foreach ( $term_items as $items ) {
				$items        = (int) $items;
				$team_members = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT um.user_id FROM {$wpdb->usermeta} um WHERE um.meta_key = %s ORDER BY CAST(um.meta_value AS unsigned) ASC",
						'user_order_' . $items
					)
				);

				foreach ( (array) $team_members as $team_member ) {
					$expected_items[ $items ][] = array(
						'user_id'   => (int) $team_member->user_id,
						'user_link' => esc_url_raw( get_author_posts_url( $team_member->user_id ) ),
					);
				}
			}
		}
	}

	$wp_wpso_array['user_items'] = $expected_items;

	wp_localize_script( 'wpso-front-scripts', 'wpso', $wp_wpso_array );
}
add_action( 'wp_enqueue_scripts', 'wp_wpso_enqueue_scripts', 99 );

function wp_wpso_admin_enqueue_scripts() {
	if ( 'wpso-settings' === wpso_get( 'page' ) && current_user_can( 'manage_options' ) ) {
		wp_enqueue_script( 'wpso-fontawesome', WPSO_URL . '/js/fontawesome.min.js', array(), WPSO_VERSION, true );
		wp_enqueue_style( 'wpso-fontawesome', WPSO_URL . '/css/fontawesome.min.css', array(), WPSO_VERSION );
		wp_enqueue_script( 'wpso-bootstrap', WPSO_URL . '/js/bootstrap.min.js', array( 'jquery' ), WPSO_VERSION, true );
		wp_enqueue_style( 'wpso-bootstrap', WPSO_URL . '/css/bootstrap.min.css', array(), WPSO_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'wp_wpso_admin_enqueue_scripts', 99 );

if ( ! function_exists( 'pre' ) ) {
	function pre( $data ) {
		if ( isset( $_GET['debug'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			pree( $data );
		}
	}
}

if ( ! function_exists( 'pree' ) ) {
	function pree( $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<pre>' . esc_html( print_r( $data, true ) ) . '</pre>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}
}

if ( ! function_exists( 'wpso_plugin_links' ) ) {
	function wpso_plugin_links( $links ) {
		global $wpso_premium_link;

		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=wpso-settings' ) ) . '">' . esc_html__( 'Settings', 'wpso-sort-order' ) . '</a>';

		if ( wpso_is_pro() ) {
			array_unshift( $links, $settings_link );
		} else {
			$go_premium = '<a href="' . esc_url( $wpso_premium_link ) . '" title="' . esc_attr__( 'Go Premium', 'wpso-sort-order' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Go Premium', 'wpso-sort-order' ) . '</a>';
			array_unshift( $links, $settings_link, $go_premium );
		}

		return $links;
	}
}

/**
 * Debug helper (administrators only): ?get_keys&post=ID dumps a post and its meta.
 */
function wpso_admin_init() {
	if ( isset( $_GET['get_keys'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpso-sort-order' ) );
		}
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id ) {
			pre( get_post( $post_id ) );
			pre( get_post_meta( $post_id ) );
			wp_die();
		}
	}
}
add_action( 'admin_init', 'wpso_admin_init' );
