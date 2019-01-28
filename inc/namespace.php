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
 *
 */
const SUPPORTS_DEFAULT = [
	'css',
	'js',
];

/**
 *
 */
const VERSION = '2.0.0';

/**
 *
 */
const BASEFILE = __FILE__;

/**
 * Namespace setup & hooks.
 */
function load() {
	add_action( 'init', __NAMESPACE__ . '\\register_post_types' );
	add_filter( 'cmb_field_types', __NAMESPACE__ . '\\register_fields' );
	add_filter( 'cmb_meta_boxes', __NAMESPACE__ . '\\register_metaboxes' );
	add_action( 'wp_ajax_cce_dependency_select', __NAMESPACE__ . '\\handle_dependency_ajax' );
}

/**
 * Register custom data post types
 */
function register_post_types() {
	$labels = [
		'singular_name'      => _x( 'File', 'post type singular name' ),
		'add_new'            => _x( 'Add New', 'file' ),
		'add_new_item'       => __( 'Add New File' ),
		'edit_item'          => __( 'Edit File' ),
		'new_item'           => __( 'New File' ),
		'view_item'          => __( 'View File' ),
		'search_items'       => __( 'Search Files' ),
		'not_found'          => __( 'No files found.' ),
		'not_found_in_trash' => __( 'No files found in Trash.' ),
		'all_items'          => __( 'All Files' ),
	];

	register_post_type(
		'cce_css', [
			'label'        => 'Custom CSS',
			'supports'     => [ 'revisions' ],
			'show_ui'      => true,
			'can_export'   => false,
			'rewrite'      => false,
			'labels'       => $labels,
			'capabilities' => [
				'edit_post'          => 'edit_theme_options',
				'read_post'          => 'read',
				'delete_post'        => 'edit_theme_options',
				'edit_posts'         => 'edit_theme_options',
				'edit_others_posts'  => 'edit_theme_options',
				'publish_posts'      => 'edit_theme_options',
				'read_private_posts' => 'read',
			],
		]
	);

	register_post_type(
		'cce_js', [
			'label'        => 'Custom JS',
			'supports'     => [ 'revisions' ],
			'show_ui'      => true,
			'can_export'   => false,
			'rewrite'      => false,
			'labels'       => $labels,
			'capabilities' => [
				'edit_post'          => 'edit_theme_options',
				'read_post'          => 'read',
				'delete_post'        => 'edit_theme_options',
				'edit_posts'         => 'edit_theme_options',
				'edit_others_posts'  => 'edit_theme_options',
				'publish_posts'      => 'edit_theme_options',
				'read_private_posts' => 'read',
			],
		]
	);
}

/**
 * Register custom fields for CMB
 *
 * Adds our dependency field to the available custom fields.
 *
 * @param array $fields Available fields
 * @return array Filtered fields
 */
function register_fields( $fields ) {
	$fields['cce_dependency'] = __NAMESPACE__ . '\\Dependency_Field';
	return $fields;
}

/**
 * Register metabox for pages.
 *
 * Allows setting dependencies on pages.
 *
 * @param array $boxes Registered metaboxes.
 * @return array
 */
function register_metaboxes( $boxes ) {
	$boxes[] = [
		'title' => __( 'File Dependencies' ),
		'pages' => 'page',
		'context' => 'advanced',
		'fields' => [
			[
				'id'              => 'dependencies_css',
				'name'            => __( 'Styles' ),
				'type'            => 'cce_dependency',
				'repeatable'      => true,
				'post_type'       => 'cce_css',
				'exclude_current' => false,
			],
			[
				'id'              => 'dependencies_js',
				'name'            => __( 'Scripts' ),
				'type'            => 'cce_dependency',
				'repeatable'      => true,
				'post_type'       => 'cce_js',
				'exclude_current' => false,
			],
		],
	];

	return $boxes;
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

	if ( empty( $type ) || ! in_array( $type, [ 'cce_css', 'cce_js' ] ) ) {
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
