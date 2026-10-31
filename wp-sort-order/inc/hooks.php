<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class & Method
 */
class Wpso {

	/**
	 * Post statuses that take part in ordering.
	 */
	const STATUSES = "'publish', 'pending', 'draft', 'private', 'future'";

	public function __construct() {
		if ( ! get_option( 'wpso_activation' ) ) {
			$this->wpso_activation();
		}

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		if ( 'wpso-settings' === wpso_get( 'page' ) ) {
			add_action( 'admin_init', array( $this, 'refresh' ) );
		}

		add_action( 'admin_init', array( $this, 'update_options' ) );
		add_action( 'admin_init', array( $this, 'update_options_premium' ) );
		add_action( 'admin_init', array( $this, 'load_script_css' ) );

		// sortable ajax actions
		add_action( 'wp_ajax_update-menu-order', array( $this, 'update_menu_order' ) );
		add_action( 'wp_ajax_update-menu-order-tags', array( $this, 'update_menu_order_tags' ) );
		add_action( 'wp_ajax_update-menu-order-users', array( $this, 'update_menu_order_users' ) );
		add_action( 'wp_ajax_update-menu-order-extras', array( $this, 'update_menu_order_extras' ) );

		// reorder post types
		add_action( 'pre_get_posts', array( $this, 'wpso_pre_get_posts' ) );

		add_filter( 'get_previous_post_where', array( $this, 'wpso_previous_post_where' ) );
		add_filter( 'get_previous_post_sort', array( $this, 'wpso_previous_post_sort' ) );
		add_filter( 'get_next_post_where', array( $this, 'hocpo_next_post_where' ) );
		add_filter( 'get_next_post_sort', array( $this, 'wpso_next_post_sort' ) );

		// reorder taxonomies
		add_filter( 'get_terms_orderby', array( $this, 'wpso_get_terms_orderby' ), 10, 3 );
		add_filter( 'wp_get_object_terms', array( $this, 'wpso_get_object_terms' ), 10, 3 );
		add_filter( 'get_terms', array( $this, 'wpso_get_object_terms' ), 10, 3 );

		// reorder users
		add_action( 'pre_user_query', array( $this, 'wpso_pre_user_query' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Users
	 * ------------------------------------------------------------------ */

	public function wpso_pre_user_query( $userquery ) {
		global $wpdb;

		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! is_object( $screen ) || 'users' !== $screen->id ) {
			return;
		}

		$valid_tax = absint( $this->current_tax( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sanitized in current_tax().
		if ( 0 === $valid_tax ) {
			return;
		}

		$userquery->query_from .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->usermeta} m1 ON {$wpdb->users}.ID = m1.user_id AND (m1.meta_key = %s)",
			'user_order_' . $valid_tax
		);

		$order                  = ( isset( $userquery->query_vars['order'] ) && 'ASC' === strtoupper( $userquery->query_vars['order'] ) ) ? 'asc' : 'desc';
		$userquery->query_orderby = ' ORDER BY CAST(m1.meta_value AS unsigned) ' . $order . ' ';
	}

	/* ------------------------------------------------------------------ *
	 * Activation / menu
	 * ------------------------------------------------------------------ */

	public function wpso_activation() {
		global $wpdb;

		if ( ! $wpdb->query( "DESCRIBE {$wpdb->terms} `term_order`" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$wpdb->terms} ADD `term_order` INT( 4 ) NULL DEFAULT '0'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		if ( ! $wpdb->query( "DESCRIBE {$wpdb->users} `user_order`" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$wpdb->users} ADD `user_order` INT( 4 ) NULL DEFAULT '0'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		update_option( 'wpso_activation', 1 );
	}

	public function wpso_load_textdomain() {
		// Kept for backward compatibility - translations are loaded on "init" from index.php.
	}

	public function admin_menu() {
		add_options_page( 'WP Sort Order', 'WP Sort Order', 'manage_options', 'wpso-settings', array( $this, 'admin_page' ) );
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
		}
		require WPSO_DIR . 'inc/settings.php';
	}

	/* ------------------------------------------------------------------ *
	 * Scripts
	 * ------------------------------------------------------------------ */

	public function _check_load_script_css() {
		global $wpso_allowed_pages;

		$active  = false;
		$objects = $this->get_wpso_options_objects();
		$tags    = $this->get_wpso_options_tags();
		$extras  = $this->get_wpso_options_extras();
		$uri     = wpso_request_uri();

		if ( ! empty( $extras ) && current_user_can( 'manage_options' ) ) {
			foreach ( (array) $wpso_allowed_pages as $page_name => $page_title ) {
				if ( in_array( $page_name, $extras, true ) && false !== strpos( $uri, 'wp-admin/' . $page_name ) ) {
					$active = true;
				}
			}
		}

		if ( empty( $objects ) && empty( $tags ) ) {
			return false;
		}

		// exclude (sorting, addnew page, edit page)
		if ( isset( $_GET['orderby'] ) || false !== strpos( $uri, 'action=edit' ) || false !== strpos( $uri, 'wp-admin/post-new.php' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$post_type = wpso_get( 'post_type' );
		$taxonomy  = wpso_get( 'taxonomy' );

		if ( ! $active && ! empty( $objects ) ) {
			if ( '' !== $post_type && '' === $taxonomy && in_array( $post_type, $objects, true ) ) { // if page or custom post types
				$active = true;
			}
			if ( '' === $post_type && false !== strpos( $uri, 'wp-admin/edit.php' ) && in_array( 'post', $objects, true ) ) { // if post
				$active = true;
			}
		}

		if ( ! $active && ! empty( $tags ) ) {
			if ( '' !== $taxonomy && in_array( $taxonomy, $tags, true ) ) {
				$active = true;
			}

			foreach ( $tags as $tag ) {
				if ( isset( $_GET[ $tag ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$active = true;
					break;
				}
			}

			if ( wpso_is_pro() && false !== strpos( $uri, 'wp-admin/users.php' ) && in_array( 'usercategories', $tags, true ) ) {
				$active = true;
			}
		}

		return $active;
	}

	public function load_script_css() {
		$translation_array = array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'this_url' => admin_url( 'admin.php?page=wpso-settings' ),
			'wpso_tab' => (string) absint( wpso_get( 't', '0' ) ),
			'nonce'    => wp_create_nonce( 'wpso_nonce_ensured' ),
		);

		if ( $this->_check_load_script_css() && wpso_user_can_sort() ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'wpsojs', WPSO_URL . '/js/scripts.js', array( 'jquery' ), WPSO_VERSION, true );
			wp_enqueue_style( 'wpso', WPSO_URL . '/css/styles.css', array(), WPSO_VERSION );
		}

		if ( is_admin() && 'wpso-settings' === wpso_get( 'page' ) && current_user_can( 'manage_options' ) ) {
			wp_enqueue_style( 'wpso', WPSO_URL . '/css/admin.css', array(), WPSO_VERSION );
			wp_enqueue_script( 'wpsojs', WPSO_URL . '/js/admin.js', array( 'jquery' ), WPSO_VERSION, true );
		}

		if ( wp_script_is( 'wpsojs', 'enqueued' ) ) {
			wp_localize_script( 'wpsojs', 'wpso_obj', $translation_array );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Re-numbering helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Re-number menu_order of the given post types.
	 *
	 * @param array  $objects Post types.
	 * @param string $mode    "keep" (current order), "page" (menu_order, title) or "date".
	 */
	private function renumber_objects( $objects, $mode = 'keep' ) {
		global $wpdb;

		foreach ( (array) $objects as $object ) {
			$result = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT count(*) as cnt, max(menu_order) as max, min(menu_order) as min FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status IN (' . self::STATUSES . ')',
					$object
				)
			);
			if ( empty( $result ) || 0 == $result[0]->cnt || $result[0]->cnt == $result[0]->max ) {
				continue;
			}

			if ( 'page' === $mode && 'page' === $object ) {
				$order_by = 'menu_order, post_title ASC';
			} elseif ( 'date' === $mode || 'page' === $mode ) {
				$order_by = 'post_date DESC';
			} else {
				$order_by = 'menu_order ASC';
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status IN (' . self::STATUSES . ') ORDER BY ' . $order_by, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed whitelist above.
					$object
				)
			);
			foreach ( (array) $results as $key => $row ) {
				$wpdb->update( $wpdb->posts, array( 'menu_order' => $key + 1 ), array( 'ID' => (int) $row->ID ), array( '%d' ), array( '%d' ) );
				clean_post_cache( (int) $row->ID );
			}
		}
	}

	/**
	 * Re-number term_order of the given taxonomies.
	 *
	 * @param array  $tags Taxonomies.
	 * @param string $sort "keep" (current order) or "name".
	 */
	private function renumber_taxonomies( $tags, $sort = 'keep' ) {
		global $wpdb;

		foreach ( (array) $tags as $taxonomy ) {
			$result = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT count(*) as cnt, max(term_order) as max, min(term_order) as min FROM {$wpdb->terms} AS terms INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) WHERE term_taxonomy.taxonomy = %s",
					$taxonomy
				)
			);
			if ( empty( $result ) || 0 == $result[0]->cnt || $result[0]->cnt == $result[0]->max ) {
				continue;
			}

			$order_by = ( 'name' === $sort ) ? 'name ASC' : 'term_order ASC';
			$results  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT terms.term_id FROM {$wpdb->terms} AS terms INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) WHERE term_taxonomy.taxonomy = %s ORDER BY " . $order_by, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed whitelist above.
					$taxonomy
				)
			);
			foreach ( (array) $results as $key => $row ) {
				$wpdb->update( $wpdb->terms, array( 'term_order' => $key + 1 ), array( 'term_id' => (int) $row->term_id ), array( '%d' ), array( '%d' ) );
			}
		}
	}

	public function refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$objects = $this->get_wpso_options_objects();
		$tags    = $this->get_wpso_options_tags();

		$this->renumber_objects( $objects, 'keep' );
		$this->renumber_taxonomies( $tags, 'keep' );

		foreach ( $tags as $term ) {
			if ( ! taxonomy_exists( $term ) ) {
				continue;
			}
			$term_items = get_terms(
				array(
					'taxonomy'   => $term,
					'hide_empty' => false,
				)
			);
			if ( empty( $term_items ) || is_wp_error( $term_items ) ) {
				continue;
			}
			foreach ( $term_items as $item ) {
				if ( ! is_object( $item ) ) {
					continue;
				}
				$this->refine_terms_relations( $item->term_id );
				$item_child = get_term_children( $item->term_id, $term );
				if ( ! empty( $item_child ) && ! is_wp_error( $item_child ) ) {
					foreach ( $item_child as $child ) {
						$this->refine_terms_relations( $child );
					}
				}
			}
		}
	}

	public function refine_terms_relations( $item ) {
		global $wpdb;

		$item = absint( $item );
		if ( ! $item ) {
			return;
		}

		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT count(*) as cnt, max(um.meta_value) as max, min(um.meta_value) as min
				FROM {$wpdb->term_relationships} AS r, {$wpdb->users} AS u, {$wpdb->usermeta} AS um
				WHERE r.term_taxonomy_id = %d AND u.ID = r.object_id
				AND (um.user_id = r.object_id AND um.meta_key = CONCAT('user_order_', r.term_taxonomy_id))",
				$item
			)
		);

		$user_count1 = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->users} u, {$wpdb->term_relationships} AS r WHERE r.term_taxonomy_id = %d AND u.ID = r.object_id",
				$item
			)
		);

		$user_count2 = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} um WHERE um.meta_key = %s",
				'user_order_' . $item
			)
		);

		if ( $user_count1 != $user_count2 ) {
			// Counts differ: drop the stored order for this term so it is rebuilt below.
			$dres = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT um.umeta_id, um.user_id FROM {$wpdb->usermeta} um WHERE um.meta_key = %s",
					'user_order_' . $item
				)
			);
			foreach ( (array) $dres as $res ) {
				$wpdb->delete( $wpdb->usermeta, array( 'umeta_id' => (int) $res->umeta_id ), array( '%d' ) );
			}
		}

		if ( ! empty( $stats ) && $stats[0]->cnt == $stats[0]->max ) {
			return;
		}

		$relations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT um.user_id, um.meta_value
				FROM {$wpdb->term_relationships} AS r, {$wpdb->users} AS u, {$wpdb->usermeta} AS um
				WHERE r.term_taxonomy_id = %d AND u.ID = r.object_id
				AND (um.user_id = r.object_id AND um.meta_key = CONCAT('user_order_', r.term_taxonomy_id))",
				$item
			)
		);

		if ( empty( $relations ) ) {
			$relations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.ID AS user_id FROM {$wpdb->term_relationships} AS r, {$wpdb->users} AS u WHERE r.term_taxonomy_id = %d AND u.ID = r.object_id",
					$item
				)
			);
		}

		foreach ( (array) $relations as $k => $res ) {
			update_user_meta( (int) $res->user_id, 'user_order_' . $item, $k + 1 );
		}
	}

	/* ------------------------------------------------------------------ *
	 * AJAX helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Common AJAX gate: capability + nonce.
	 *
	 * @param bool $admin_only Restrict to administrators regardless of pro role settings.
	 */
	private function ajax_gate( $admin_only = false ) {
		$allowed = $admin_only ? current_user_can( 'manage_options' ) : wpso_user_can_sort();
		if ( ! $allowed ) {
			wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
		}

		$nonce = isset( $_POST['wpso_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpso_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wpso_nonce_ensured' ) ) {
			wp_die( esc_html__( 'Sorry! Your nonce did not verify.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Parse jQuery UI "serialize()" output into a flat, ordered list of integer ids.
	 *
	 * @return int[]
	 */
	private function parse_order_ids() {
		$raw = isset( $_POST['order'] ) && is_string( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed + cast to int below.
		if ( '' === $raw || strlen( $raw ) > 50000 ) {
			return array();
		}
		parse_str( $raw, $data );

		$ids = array();
		if ( is_array( $data ) ) {
			foreach ( $data as $values ) {
				if ( ! is_array( $values ) ) {
					continue;
				}
				foreach ( $values as $id ) {
					if ( is_scalar( $id ) && absint( $id ) ) {
						$ids[] = absint( $id );
					}
				}
			}
		}
		return array_slice( $ids, 0, 2000 );
	}

	public function update_menu_order() {
		global $wpdb;

		$this->ajax_gate();
		$ids = $this->parse_order_ids();

		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
			}
		}

		do_action( 'wpso_before_order_update', 'post', $ids );

		$counter = 0;
		foreach ( $ids as $id ) {
			++$counter;
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $counter ), array( 'ID' => $id ), array( '%d' ), array( '%d' ) );
			clean_post_cache( $id );
		}

		do_action( 'wpso_order_updated', 'post', $ids );
		wp_die();
	}

	public function wpso_increment_order( $arr = array(), $order = 0 ) {
		while ( in_array( $order, $arr, true ) ) {
			++$order;
		}
		return $order;
	}

	public function update_menu_order_tags() {
		global $wpdb;

		$this->ajax_gate();
		$ids = $this->parse_order_ids();

		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_term', $id ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
			}
		}

		do_action( 'wpso_before_order_update', 'term', $ids );

		$menu_order_arr = array();
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT term_order FROM {$wpdb->terms} WHERE term_id = %d", $id ) );
			if ( is_object( $row ) ) {
				$menu_order_arr[] = $this->wpso_increment_order( $menu_order_arr, (int) $row->term_order );
			}
		}
		sort( $menu_order_arr );

		foreach ( $ids as $position => $id ) {
			if ( array_key_exists( $position, $menu_order_arr ) ) {
				$wpdb->update( $wpdb->terms, array( 'term_order' => (int) $menu_order_arr[ $position ] ), array( 'term_id' => $id ), array( '%d' ), array( '%d' ) );
			}
		}
		clean_term_cache( $ids );

		do_action( 'wpso_order_updated', 'term', $ids );
		wp_die();
	}

	public function current_tax( $params ) {
		$valid_tax = 0;
		if ( ! empty( $params ) && is_array( $params ) ) {
			$tags = $this->get_wpso_options_tags();
			foreach ( $params as $tax => $term ) {
				if ( ! is_string( $tax ) || ! is_string( $term ) || ! in_array( $tax, $tags, true ) ) {
					continue;
				}
				$obj = get_term_by( 'slug', sanitize_text_field( wp_unslash( $term ) ), $tax );
				if ( is_object( $obj ) && isset( $obj->term_id ) ) {
					$valid_tax = (int) $obj->term_id;
					break;
				}
			}
		}
		return $valid_tax;
	}

	public function update_menu_order_users() {
		$this->ajax_gate();

		$ids     = $this->parse_order_ids();
		$referer = isset( $_POST['referer_string'] ) && is_string( $_POST['referer_string'] ) ? wp_unslash( $_POST['referer_string'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed, every value re-sanitized in current_tax().
		$params  = array();
		if ( strlen( $referer ) < 2000 ) {
			parse_str( $referer, $params );
		}

		$valid_tax = $this->current_tax( $params );
		if ( ! $valid_tax ) {
			wp_die();
		}

		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_user', $id ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
			}
		}

		do_action( 'wpso_before_order_update', 'user', $ids );

		$user_order     = 'user_order_' . $valid_tax;
		$menu_order_arr = array();
		foreach ( $ids as $id ) {
			$menu_order_arr[] = get_user_meta( $id, $user_order, true );
		}
		sort( $menu_order_arr );

		foreach ( $ids as $position => $id ) {
			$value = isset( $menu_order_arr[ $position ] ) && is_numeric( $menu_order_arr[ $position ] ) ? (int) $menu_order_arr[ $position ] : '';
			update_user_meta( $id, $user_order, $value );
		}

		do_action( 'wpso_order_updated', 'user', $ids );
		wp_die();
	}

	public function update_menu_order_extras() {
		$this->ajax_gate( true );

		$ids = array();
		$raw = isset( $_POST['order'] ) && is_string( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is filtered below.
		if ( '' !== $raw && strlen( $raw ) < 50000 ) {
			parse_str( $raw, $data );
			if ( is_array( $data ) ) {
				foreach ( $data as $values ) {
					if ( ! is_array( $values ) ) {
						continue;
					}
					foreach ( $values as $position => $id ) {
						if ( is_scalar( $id ) ) {
							$ids[ absint( $position ) ] = preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $id );
						}
					}
				}
			}
		}

		update_option( 'wpso_extras_order', $ids, false );
		wp_die();
	}

	/* ------------------------------------------------------------------ *
	 * Settings
	 * ------------------------------------------------------------------ */

	private function settings_redirect( $tab ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wpso-settings',
					'msg'  => 'update',
					't'    => absint( $tab ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function update_options() {
		if ( ! isset( $_POST['wpso_submit'] ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'nonce_wpso' );

		global $wpso_allowed_pages, $premium_tags_list;

		$objects = isset( $_POST['objects'] ) ? (array) wp_unslash( $_POST['objects'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- whitelisted below.
		$tags    = isset( $_POST['tags'] ) ? (array) wp_unslash( $_POST['tags'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- whitelisted below.
		$extras  = isset( $_POST['extras'] ) ? (array) wp_unslash( $_POST['extras'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- whitelisted below.

		$objects = array_values( array_filter( array_unique( array_map( 'sanitize_key', $objects ) ), 'post_type_exists' ) );
		$tags    = array_values( array_filter( array_unique( array_map( 'sanitize_key', $tags ) ), 'taxonomy_exists' ) );
		$extras  = array_values( array_intersect( array_map( 'sanitize_text_field', $extras ), array_keys( (array) $wpso_allowed_pages ) ) );

		// Premium taxonomies are saved from the Premium tab - keep them.
		foreach ( $this->get_wpso_options_tags() as $old_tag ) {
			if ( in_array( $old_tag, (array) $premium_tags_list, true ) && ! in_array( $old_tag, $tags, true ) ) {
				$tags[] = $old_tag;
			}
		}

		update_option(
			'wpso_options',
			array(
				'objects' => $objects ? $objects : '',
				'tags'    => $tags ? $tags : '',
				'extras'  => $extras ? $extras : '',
			)
		);

		$this->renumber_objects( $this->get_wpso_options_objects(), 'page' );
		$this->renumber_taxonomies( $this->get_wpso_options_tags(), 'name' );

		$tab = isset( $_POST['wpso_tn'] ) ? absint( $_POST['wpso_tn'] ) : 0;
		$this->settings_redirect( $tab );
	}

	public function update_options_premium() {
		global $premium_tags_list;

		if ( ! isset( $_POST['wpso_premium_submit'] ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'nonce_wpso' );

		$tags_old       = $this->get_wpso_options_tags();
		$input_options  = get_option( 'wpso_options', array() );
		$input_options  = is_array( $input_options ) ? $input_options : array();
		$tags_premium   = isset( $_POST['tags'] ) ? (array) wp_unslash( $_POST['tags'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- whitelisted below.
		$tags_premium   = array_values( array_intersect( array_map( 'sanitize_key', $tags_premium ), (array) $premium_tags_list ) );
		$tags_premium   = array_filter( $tags_premium, 'taxonomy_exists' );

		if ( ! empty( $tags_premium ) ) {
			$new_tags = array_unique( array_merge( $tags_old, $tags_premium ) );
		} else {
			$new_tags = array_diff( $tags_old, (array) $premium_tags_list );
		}

		$input_options['tags'] = array_values( $new_tags );
		update_option( 'wpso_options', $input_options );

		$this->renumber_taxonomies( $this->get_wpso_options_tags(), 'name' );

		$tab = isset( $_POST['wpso_tn'] ) ? absint( $_POST['wpso_tn'] ) : 0;
		$this->settings_redirect( $tab );
	}

	/* ------------------------------------------------------------------ *
	 * Front-end ordering
	 * ------------------------------------------------------------------ */

	public function wpso_previous_post_where( $where ) {
		global $post;
		$objects = $this->get_wpso_options_objects();
		if ( empty( $objects ) ) {
			return $where;
		}
		if ( is_object( $post ) && isset( $post->post_type ) && in_array( $post->post_type, $objects, true ) ) {
			$where = str_replace( "p.post_date < '" . $post->post_date . "'", 'p.menu_order > ' . (int) $post->menu_order, $where );
		}
		return $where;
	}

	public function wpso_previous_post_sort( $orderby ) {
		global $post;
		$objects = $this->get_wpso_options_objects();
		if ( empty( $objects ) ) {
			return $orderby;
		}
		if ( is_object( $post ) && isset( $post->post_type ) && in_array( $post->post_type, $objects, true ) ) {
			$orderby = 'ORDER BY p.menu_order ASC LIMIT 1';
		}
		return $orderby;
	}

	public function hocpo_next_post_where( $where ) {
		global $post;
		$objects = $this->get_wpso_options_objects();
		if ( empty( $objects ) ) {
			return $where;
		}
		if ( is_object( $post ) && isset( $post->post_type ) && in_array( $post->post_type, $objects, true ) ) {
			$where = str_replace( "p.post_date > '" . $post->post_date . "'", 'p.menu_order < ' . (int) $post->menu_order, $where );
		}
		return $where;
	}

	public function wpso_next_post_sort( $orderby ) {
		global $post;
		$objects = $this->get_wpso_options_objects();
		if ( empty( $objects ) ) {
			return $orderby;
		}
		if ( is_object( $post ) && isset( $post->post_type ) && in_array( $post->post_type, $objects, true ) ) {
			$orderby = 'ORDER BY p.menu_order DESC LIMIT 1';
		}
		return $orderby;
	}

	public function wpso_pre_get_posts( $wp_query ) {
		$objects = $this->get_wpso_options_objects();
		if ( empty( $objects ) ) {
			return false;
		}

		if ( is_admin() ) {
			if ( isset( $wp_query->query['post_type'] ) && ! isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( is_string( $wp_query->query['post_type'] ) && in_array( $wp_query->query['post_type'], $objects, true ) ) {
					$wp_query->set( 'orderby', 'menu_order' );
					$wp_query->set( 'order', 'ASC' );
				}
			}
			return;
		}

		/**
		 * For Front End
		 */
		$home_url    = basename( home_url() );
		$request_uri = basename( wpso_request_uri() );

		if ( $home_url === $request_uri ) {
			return;
		}

		$active = false;
		if ( isset( $wp_query->query['post_type'] ) ) {
			if ( is_string( $wp_query->query['post_type'] ) && in_array( $wp_query->query['post_type'], $objects, true ) ) {
				$active = true;
			}
		} elseif ( in_array( 'post', $objects, true ) ) {
			$active = true;
		}

		if ( ! $active ) {
			return false;
		}

		if ( isset( $wp_query->query['suppress_filters'] ) ) {
			if ( 'date' === $wp_query->get( 'orderby' ) ) {
				$wp_query->set( 'orderby', 'menu_order' );
			}
			if ( 'DESC' === $wp_query->get( 'order' ) ) {
				$wp_query->set( 'order', 'ASC' );
			}
		} else {
			if ( ! $wp_query->get( 'orderby' ) ) {
				$wp_query->set( 'orderby', 'menu_order' );
			}
			if ( ! $wp_query->get( 'order' ) ) {
				$wp_query->set( 'order', 'ASC' );
			}
		}
	}

	public function wpso_get_terms_orderby( $orderby, $args ) {
		if ( is_admin() ) {
			return $orderby;
		}
		$tags = $this->get_wpso_options_tags();
		if ( ! isset( $args['taxonomy'] ) || ! is_string( $args['taxonomy'] ) ) {
			return $orderby;
		}
		if ( ! in_array( $args['taxonomy'], $tags, true ) ) {
			return $orderby;
		}
		return 't.term_order';
	}

	public function wpso_get_object_terms( $terms ) {
		if ( ! is_array( $terms ) ) {
			return $terms;
		}
		$tags = $this->get_wpso_options_tags();
		if ( is_admin() && isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $terms;
		}
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, $tags, true ) ) {
				return $terms;
			}
		}
		usort( $terms, array( $this, 'taxcmp' ) );
		return $terms;
	}

	public function taxcmp( $a, $b ) {
		$a_order = isset( $a->term_order ) ? (int) $a->term_order : 0;
		$b_order = isset( $b->term_order ) ? (int) $b->term_order : 0;
		if ( $a_order === $b_order ) {
			return 0;
		}
		return ( $a_order < $b_order ) ? -1 : 1;
	}

	/* ------------------------------------------------------------------ *
	 * Options
	 * ------------------------------------------------------------------ */

	private function get_option_list( $key ) {
		$wpso_options = get_option( 'wpso_options' );
		$wpso_options = is_array( $wpso_options ) ? $wpso_options : array();
		return ( isset( $wpso_options[ $key ] ) && is_array( $wpso_options[ $key ] ) ) ? array_values( array_filter( $wpso_options[ $key ], 'is_string' ) ) : array();
	}

	public function get_wpso_options_objects() {
		return $this->get_option_list( 'objects' );
	}

	public function get_wpso_options_tags() {
		return $this->get_option_list( 'tags' );
	}

	public function get_wpso_options_extras() {
		return $this->get_option_list( 'extras' );
	}
}

global $wpso;
$wpso = new Wpso();

/**
 * Newly created posts go to the top.
 */
function wpso_updating_new_post_menu_order( $post_id, $post, $update ) {
	global $wpdb;

	if ( wp_is_post_revision( $post_id ) || $update ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$wpso_options = get_option( 'wpso_options' );
	$wpso_objects = ( is_array( $wpso_options ) && isset( $wpso_options['objects'] ) && is_array( $wpso_options['objects'] ) ) ? $wpso_options['objects'] : array();

	if ( is_object( $post ) && ! $post->menu_order && in_array( $post->post_type, $wpso_objects, true ) ) {
		$min = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT MIN(menu_order) FROM {$wpdb->posts} WHERE post_type = %s", $post->post_type )
		);
		wp_update_post(
			array(
				'ID'         => $post->ID,
				'menu_order' => $min - 1,
			)
		);
	}
}
add_action( 'save_post', 'wpso_updating_new_post_menu_order', 10, 3 );

/**
 * Apply the saved plugin order on the Plugins screen.
 */
function wpso_admin_scripts() {
	global $wpso;

	if ( ! is_object( $wpso ) || ! current_user_can( 'manage_options' ) || ! $wpso->_check_load_script_css() ) {
		return;
	}

	$order = get_option( 'wpso_extras_order' );
	if ( empty( $order ) || ! is_array( $order ) ) {
		return;
	}

	$map = array();
	foreach ( $order as $position => $key ) {
		$map[ 'plugin_' . preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $key ) ] = (int) $position;
	}
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($){
		setTimeout(function(){
			var wpsoMap = <?php echo wp_json_encode( $map ); ?>;
			$.each(wpsoMap, function(id, order){
				var el = document.getElementById(id);
				if (el) { $(el).attr('data-order', order); }
			});
			var list = $('table.plugins:visible #the-list');
			var listItems = list.find('tr').sort(function (a, b) {
				var contentA = parseInt($(a).attr('data-order'));
				var contentB = parseInt($(b).attr('data-order'));
				return (contentA < contentB) ? -1 : (contentA > contentB) ? 1 : 0;
			});
			list.find('tr').remove();
			list.append(listItems);
			return false;
		}, 200);
	});
	</script>
	<?php
}
add_action( 'admin_footer', 'wpso_admin_scripts', 100 );
