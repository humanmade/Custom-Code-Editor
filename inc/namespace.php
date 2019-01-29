<?php
/**
 * Created by PhpStorm.
 * User: mikeselander
 * Date: 2019-01-28
 * Time: 15:03
 */

namespace CustomCodeEditor;

use WP_Query;

/**
 * Current version of the plugin.
 */
const VERSION = '2.0.0';

/**
 * Namespace setup & hooks.
 */
function load() {
	add_action( 'wp_ajax_cce_dependency_select', __NAMESPACE__ . '\\handle_dependency_ajax' );
}

/**
 * Get available languages.
 *
 * @return array
 */
function get_used_languages() : array {
	/**
	 * Filter which languages are available on the site.
	 *
	 * @param array $languages
	 */
	return apply_filters( 'cce_languages', [ 'css', 'js' ] );
}

/**
 * Get available language post types.
 *
 * @return array
 */
function get_used_post_types() : array {
	return array_map( function( $language ) {
		return 'cce_' . $language;
	}, get_used_languages() );
}

/**
 * Handle Ajax requests for the dependency field.
 *
 * Handles fetching file IDs for the dependency field Ajax requests
 */
function handle_dependency_ajax() {

	$post_id         = empty( $_POST['post_id'] ) ? false : intval( $_POST['post_id'] );
	$nonce           = empty( $_POST['nonce'] ) ? false : $_POST['nonce'];
	$type            = empty( $_POST['post_type'] ) ? null : $_POST['post_type'];
	$page            = empty( $_POST['page'] ) ? 1 : absint( $_POST['page'] );
	$exclude_current = empty( $_POST['exclude_current'] ) ? false : true;

	$can_edit = false;
	if ( ! empty( $post_id ) ) {
		$can_edit = current_user_can( 'edit_post', $post_id );
	}

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cce_dependency_field' ) || ! $can_edit ) {
		return wp_send_json_error(
			[
				'total' => 0,
				'posts' => [],
			]
		);
	}

	if ( empty( $type ) || ! in_array( $type, [ Post_Types\JS_SLUG, Post_Types\JS_SLUG ], true ) ) {
		return wp_send_json_error(
			[
				'total' => 0,
				'posts' => [],
			]
		);
	}

	$args = [
		'post_type'      => $type,
		'fields'         => 'ids',
		'paged'          => $page,
		'posts_per_page' => 10,
	];

	if ( $exclude_current ) {
		$args['post__not_in'] = [ $post_id ];
	}

	$query = new WP_Query( $args );

	$json = [
		'total' => $query->found_posts,
		'posts' => [],
	];

	foreach ( $query->posts as $post_id ) {
		array_push(
			$json['posts'], [
				'id' => $post_id,
				'text' => html_entity_decode( get_the_title( $post_id ) ),
			]
		);
	}

	header( 'Content-Type: application/json' );
	echo json_encode( $json );
	exit;
}
