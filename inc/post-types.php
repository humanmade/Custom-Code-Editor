<?php
/**
 * Declare post type support for files.
 *
 * @package
 */

namespace CustomCodeEditor\Post_Types;

use CustomCodeEditor;

const CSS_SLUG = 'cce_css';

const JS_SLUG = 'cce_js';

/**
 * Register each post type  as desired by a developer.
 */
function load() {
	$languages = CustomCodeEditor\get_used_languages();

	if ( in_array( 'css', $languages, true ) ) {
		add_action( 'init', __NAMESPACE__ . '\\register_post_type_css' );
	}

	if ( in_array( 'js', $languages, true ) ) {
		add_action( 'init', __NAMESPACE__ . '\\register_post_type_js' );
	}
}

/**
 * Labels that are shared across post types.
 *
 * @return array
 */
function shared_args() {
	return [
		'supports'   => [ 'revisions' ],
		'show_ui'    => true,
		'can_export' => false,
		'rewrite'    => false,
		'labels'     => [
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
		],
		'capabilities' => [
			'edit_post'          => 'edit_theme_options',
			'read_post'          => 'read',
			'delete_post'        => 'edit_theme_options',
			'edit_posts'         => 'edit_theme_options',
			'edit_others_posts'  => 'edit_theme_options',
			'publish_posts'      => 'edit_theme_options',
			'read_private_posts' => 'read',
		],
	];
}

/**
 * Register CSS post type.
 */
function register_post_type_css() {
	register_post_type(
		CSS_SLUG,
		wp_parse_args(
			[
				'label' => __( 'Custom CSS' ),
			],
			shared_args()
		)
	);
}

/**
 * Register JS post type.
 */
function register_post_type_js() {
	register_post_type(
		JS_SLUG,
		wp_parse_args(
			[
				'label' => __( 'Custom JS' ),
			],
			shared_args()
		)
	);
}
