<?php
/*
Plugin Name: Custom Code Editor
Plugin URI: https://github.com/humanmade/Custom-Code-Editor
Description: Lets you add custom code snippets on a global, per-page or dependency basis. Requires Human Made's Custom Meta Boxes for per-page and dependency features.
Version: 1.0.1
License: GPL-2.0+
Author: Human Made Limited
Author URI: http://hmn.md

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace CustomCodeEditor;

use WP_Query;

const BASEFILE = __FILE__;

spl_autoload_register( __NAMESPACE__ . '\\autoload' );

require __DIR__ . '/inc/editor.php';
require __DIR__ . '/inc/frontend.php';

add_action( 'init',                          __NAMESPACE__ . '\\register_post_types' );
add_filter( 'cmb_field_types',               __NAMESPACE__ . '\\register_fields' );
add_filter( 'cmb_meta_boxes',                __NAMESPACE__ . '\\register_metaboxes' );
add_action( 'wp_ajax_cce_dependency_select', __NAMESPACE__ . '\\handle_dependency_ajax' );

Editor\load();
Frontend\load();

/**
 * Autoload our classes in
 *
 * @param string $class
 */
function autoload( $class ) {
	if ( strpos( $class, 'CustomCodeEditor' ) !== 0 ) {
		return;
	}

	$class = str_replace( 'CustomCodeEditor', '', $class );
	$file = strtolower( $class );
	$file = str_replace( '\\', '/', $file );
	$parts = explode( '/', $file );
	$last = count( $parts ) - 1;
	$parts[ $last ] = 'class-' . str_replace( '_', '-', $parts[ $last ] ) . '.php';
	$path = __DIR__ . '/inc/' . implode( '/', $parts );

	if ( file_exists( $path ) ) {
		require $path;
	}
}

/**
 * Register custom data post types
 */
function register_post_types() {
	$labels = array(
		'singular_name'      => _x('File', 'post type singular name'),
		'add_new'            => _x('Add New', 'file'),
		'add_new_item'       => __('Add New File'),
		'edit_item'          => __('Edit File'),
		'new_item'           => __('New File'),
		'view_item'          => __('View File'),
		'search_items'       => __('Search Files'),
		'not_found'          => __('No files found.'),
		'not_found_in_trash' => __('No files found in Trash.'),
		'all_items'          => __( 'All Files' ),
	);

	register_post_type( 'cce_css', array(
		'label'        => 'Custom CSS',
		'supports'     => array( 'revisions' ),
		'show_ui'      => true,
		'can_export'   => false,
		'rewrite'      => false,
		'labels'       => $labels,
		'capabilities' => array(
			'edit_post'          => 'edit_theme_options',
			'read_post'          => 'read',
			'delete_post'        => 'edit_theme_options',
			'edit_posts'         => 'edit_theme_options',
			'edit_others_posts'  => 'edit_theme_options',
			'publish_posts'      => 'edit_theme_options',
			'read_private_posts' => 'read'
		),
	) );

	register_post_type( 'cce_js', array(
		'label'        => 'Custom JS',
		'supports'     => array( 'revisions' ),
		'show_ui'      => true,
		'can_export'   => false,
		'rewrite'      => false,
		'labels'       => $labels,
		'capabilities' => array(
			'edit_post'          => 'edit_theme_options',
			'read_post'          => 'read',
			'delete_post'        => 'edit_theme_options',
			'edit_posts'         => 'edit_theme_options',
			'edit_others_posts'  => 'edit_theme_options',
			'publish_posts'      => 'edit_theme_options',
			'read_private_posts' => 'read'
		)
	) );
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
 * Register metabox for pages
 *
 * Allows setting dependencies on pages.
 *
 * @param array $boxes Registered metaboxes
 * @return array
 */
function register_metaboxes( $boxes ) {
	$boxes[] = array(
		'title' => __( 'File Dependencies' ),
		'pages' => 'page',
		'context' => 'advanced',
		'fields' => array(
			array(
				'id'              => 'dependencies_css',
				'name'            => __( 'Styles' ),
				'type'            => 'cce_dependency',
				'repeatable'      => true,
				'post_type'       => 'cce_css',
				'exclude_current' => false,
			),
			array(
				'id'              => 'dependencies_js',
				'name'            => __( 'Scripts' ),
				'type'            => 'cce_dependency',
				'repeatable'      => true,
				'post_type'       => 'cce_js',
				'exclude_current' => false,
			),
		),
	);

	return $boxes;
}

/**
 * Handle Ajax requests for the dependency field
 *
 * Handles fetching file IDs for the dependency field Ajax requests
 */
function handle_dependency_ajax() {

	$post_id         = empty( $_POST['post_id'] )         ? false : intval( $_POST['post_id'] );
	$nonce           = empty( $_POST['nonce'] )           ? false : $_POST['nonce'];
	$type            = empty( $_POST['post_type'] )       ? null  : $_POST['post_type'];
	$page            = empty( $_POST['page'] )            ? 1     : absint( $_POST['page'] );
	$exclude_current = empty( $_POST['exclude_current'] ) ? false : true;

	$can_edit = false;
	if ( ! empty( $post_id ) ) {
		$can_edit = current_user_can( 'edit_post', $post_id );
	}

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cce_dependency_field' ) || ! $can_edit ) {
		return wp_send_json_error( array( 'total' => 0, 'posts' => array() ) );
	}

	if ( empty( $type ) || ! in_array( $type, array( 'cce_css', 'cce_js' ) ) ) {
		return wp_send_json_error( array( 'total' => 0, 'posts' => array() ) );
	}

	$args = array(
		'post_type'      => $type,
		'fields'         => 'ids',
		'paged'          => $page,
		'posts_per_page' => 10,
	);
	if ( $exclude_current ) {
		$args['post__not_in'] = array( $post_id );
	}

	$query = new WP_Query( $args );

	$json = array( 'total' => $query->found_posts, 'posts' => array() );

	foreach ( $query->posts as $post_id ) {
		array_push( $json['posts'], array( 'id' => $post_id, 'text' => html_entity_decode( get_the_title( $post_id ) ) ) );
	}

	header( 'Content-Type: application/json' );
	echo json_encode( $json );
	exit;
}
