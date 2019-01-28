<?php
/**
 * Handles frontend CSS & JS display.
 *
 * @package CustomCodeEditor
 */

namespace CustomCodeEditor\Frontend;

use CustomCodeEditor\Post_Types;
use WP_Query;

/**
 * Load the frontend actions.
 */
function load() {
	add_action( 'wp_ajax_cce-file', __NAMESPACE__ . '\\handle_file_request' );
	add_action( 'wp_ajax_nopriv_cce-file', __NAMESPACE__ . '\\handle_file_request' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\register_files' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_page_files', 100 );
}

/**
 * Register all custom files with WP.
 *
 * @see WP_Dependencies
 * @see wp_enqueue_script
 * @see wp_enqueue_style
 *
 * Uses underlying WP_Dependencies object behind wp_enqueue_script
 * and wp_enqueue_style.
 */
function register_files() {
	$query = new WP_Query();
	$args  = [
		'post_type'      => [ Post_Types\CSS_SLUG, Post_Types\JS_SLUG ],
		'posts_per_page' => -1,
	];
	$files = $query->query( $args );

	// Register all files.
	foreach ( $files as $file ) {
		switch ( $file->post_type ) {
			case Post_Types\CSS_SLUG:
				$handler = $GLOBALS['wp_styles'];
				break;

			case Post_Types\JS_SLUG:
				$handler = $GLOBALS['wp_scripts'];
				break;
		}

		// Sanity check.
		if ( empty( $handler ) ) {
			continue;
		}

		$handle = 'cce-file-' . $file->ID;

		$url  = admin_url( 'admin-ajax.php' );
		$args = [
			'action' => 'cce-file',
			'id'     => $file->ID,
		];
		$url  = add_query_arg( $args, $url );

		// Fetch dependencies from the database.
		$deps = get_post_meta( $file->ID, 'dependencies', false );
		$deps = array_map(
			function ( $id ) {
					return 'cce-file-' . $id;
			}, $deps
		);

		if ( empty( $deps ) ) {
			$deps = [];
		}

		// Use last-modified time as version.
		$version = mysql2date( 'YmdHis', $file->post_modified, false );

		// Register the script.
		$handler->add( $handle, $url, $deps, $version );

		// Should we actually enqueue this one?
		$is_active = false;
		$is_global = get_post_meta( $file->ID, 'global', true );
		if ( ! empty( $is_global ) ) {
			$is_active = true;
		}

		/**
		 * Is the file active?
		 *
		 * Determines whether the given file is marked as active. If the file is
		 * active, we enqueue it on to the current page.
		 *
		 * @param boolean $is_active Is this file active?
		 * @param string $handle File handle.
		 * @param WP_Post $file File post data.
		 */
		$is_active = apply_filters( 'cce_file_is_active', $is_active, $handle, $file );
		if ( $is_active ) {
			$handler->enqueue( $handle );
		}
	}
}

/**
 * Enqueue all files listed as dependencies for the current page.
 */
function enqueue_page_files() {
	global $wp_query;
	if ( ! $wp_query->is_page() ) {
		return;
	}

	$page = $wp_query->posts[0];
	if ( $page->post_type !== 'page' ) {
		return;
	}

	$scripts = get_post_meta( $page->ID, 'dependencies_js', false );
	foreach ( $scripts as $file_id ) {
		wp_enqueue_script( 'cce-file-' . $file_id );
	}

	$styles = get_post_meta( $page->ID, 'dependencies_css', false );
	foreach ( $styles as $file_id ) {
		wp_enqueue_style( 'cce-file-' . $file_id );
	}
}

/**
 * Serve up an individual file.
 *
 * Handles HTTP requests for CSS/JS files.
 */
function handle_file_request() {
	if ( empty( $_GET['id'] ) || ! is_numeric( $_GET['id'] ) ) {
		exit;
	}

	$post_id = absint( $_GET['id'] );
	$file    = get_post( $post_id );
	if ( empty( $file ) ) {
		exit;
	}

	switch ( $file->post_type ) {
		case Post_Types\CSS_SLUG:
			header( 'Content-Type: text/css; charset=UTF-8' );
			break;

		case Post_Types\JS_SLUG:
			header( 'Content-Type: application/javascript; charset=UTF-8' );
			break;

		default:
			exit;
	}

	$expires_offset = 31536000; // 1 year.

	header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expires_offset ) . ' GMT' );
	header( "Cache-Control: public, max-age=$expires_offset" );

	echo $file->post_content;
	exit;
}
