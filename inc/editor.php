<?php

namespace CustomCodeEditor\Editor;

use CustomCodeEditor;

/**
 * Load the editor actions
 */
function load() {
	// UI
	add_action( 'admin_enqueue_scripts',         __NAMESPACE__ . '\\enqueue_styles' );
	add_action( 'edit_form_after_title',         __NAMESPACE__ . '\\output_file_name_field' );
	add_action( 'edit_form_after_editor',        __NAMESPACE__ . '\\output_editor' );
	add_filter( 'cmb_meta_boxes',                __NAMESPACE__ . '\\register_metaboxes' );
	add_action( 'add_meta_boxes_cce_css',        __NAMESPACE__ . '\\correct_meta_boxes' );
	add_action( 'add_meta_boxes_cce_js',         __NAMESPACE__ . '\\correct_meta_boxes' );

	// Backend
	add_filter( 'wp_insert_post_empty_content',  __NAMESPACE__ . '\\is_empty_post', 10, 2 );
	add_filter( 'wp_insert_post_data',           __NAMESPACE__ . '\\sanitize_post_data', 10, 2 );
}

/**
 * Output filename field
 *
 * Acts as the post title for custom files.
 *
 * @param WP_Post $post
 */
function output_file_name_field( $post ) {
	switch ( $post->post_type ) {
		case 'cce_js':
			$placeholder = 'file.js';
			break;

		case 'cce_css':
			$placeholder = 'file.css';
			break;

		default:
			return;
	}
	?>

	<div id="titlediv">
		<div id="titlewrap">
			<input type="text" name="post_title" id="title"
				value="<?php echo esc_attr( htmlspecialchars( $post->post_title ) ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ) ?>"
				class="code" size="30" autocomplete="off" />
		</div>
	</div>

	<?php
}

/**
 * Output HTML for the editor
 *
 * Unlike the built-in editor, this doesn't ever use TinyMCE, but rather inserts
 * our own custom textarea. However, it uses the correct name (`content`) so
 * that WP handles it correctly.
 *
 * @param WP_Post $post
 */
function output_editor( $post ) {
	$args = array();

	switch ( $post->post_type ) {
		case 'cce_js':
			$placeholder = '// Your custom JS lives here.';
			$args['type'] = 'javascript';
			break;

		case 'cce_css';
			$placeholder = '/* Your custom CSS lives here. */';
			$args['type'] = 'css';
			break;

		default:
			return;
	}

	enqueue_scripts( $args );
	?>
		<textarea id="cce_file_editor" name="content"
			placeholder="<?php echo esc_attr( $placeholder ) ?>"><?php echo esc_textarea( $post->post_content ) ?></textarea>
	<?php
}

/**
 * Correct the metaboxes on the file editor pages
 *
 * Moves the revisions metabox to the side, rather than underneath the editor.
 */
function correct_meta_boxes() {
	global $wp_meta_boxes;

	$page = get_current_screen()->id;

	// Move revisions to the side
	if ( isset( $wp_meta_boxes[$page]['normal']['core']['revisionsdiv'] ) ) {
		remove_meta_box( 'revisionsdiv', null, 'normal' );
		add_meta_box( 'revisionsdiv', __( 'Revisions' ), 'post_revisions_meta_box', null, 'side', 'low' );
	}
}

/**
 * Register dependency metaboxes for file editor pages
 *
 * @param array $boxes Custom Meta Boxes data
 * @return array
 */
function register_metaboxes( $boxes ) {
	// Dependency fields
	$types = array( 'cce_js', 'cce_css' );
	foreach ( $types as $type ) {
		$fields = array(
			array(
				'id' => 'dependencies',
				'name' => '',
				'type' => 'cce_dependency',
				'repeatable' => true,
				'post_type' => $type,
			),
		);
		$boxes[] = array(
			'title' => __( 'Dependencies' ),
			'pages' => $type,
			'fields' => $fields,
		);
	}

	$boxes[] = array(
		'title' => __( 'File Properties' ),
		'pages' => $types,
		'context' => 'side',
		'priority' => 'high',
		'fields' => array(
			array(
				'id' => 'global',
				'name' => __( 'Load globally' ),
				'type' => 'checkbox',
			),
		),
	);

	return $boxes;
}

/**
 * Enqueue the scripts needed for the editor
 *
 * Called via {@see output_editor}, as it's not needed on every page.
 *
 * @param array $args Editor arguments (including `type` for current file type)
 */
function enqueue_scripts( $args ) {
	$prefix = 'cce-file-editor-';

	$scripts = array(
		$prefix . 'cm' => array(
			'path' => 'assets/codemirror/lib/codemirror.js',
		),

		// Linter
		$prefix . 'jshint' => array(
			'path' => 'assets/jshint.js',
		),
		$prefix . 'csslint' => array(
			'path' => 'assets/csslint.js',
		),
		$prefix . 'cm-lint' => array(
			'path' => 'assets/codemirror/addon/lint/lint.js',
		),
		$prefix . 'cm-lint-javascript' => array(
			'path' => 'assets/codemirror/addon/lint/javascript-lint.js',
			'deps' => array(
				$prefix . 'jshint',
				$prefix . 'cm-lint',
			),
		),
		$prefix . 'cm-lint-css' => array(
			'path' => 'assets/codemirror/addon/lint/css-lint.js',
			'deps' => array(
				$prefix . 'csslint',
				$prefix . 'cm-lint',
			),
		),

		// Placeholder
		$prefix . 'cm-placeholder' => array(
			'path' => 'assets/codemirror/addon/display/placeholder.js',
		),

		// Modes
		$prefix . 'cm-mode-css' => array(
			'path' => 'assets/codemirror/mode/css/css.js',
		),
		$prefix . 'cm-mode-javascript' => array(
			'path' => 'assets/codemirror/mode/javascript/javascript.js',
		),
	);

	$defaults = array(
		'deps'      => array(),
		'version'   => '4.3',
		'in_footer' => true,
	);

	foreach ( $scripts as $handle => $script ) {
		$script = wp_parse_args( $script, $defaults );

		// Work out correct URL
		$url = plugins_url( $script['path'], CustomCodeEditor\BASEFILE );

		wp_register_script( $handle, $url, $script['deps'], $script['version'], $script['in_footer'] );
	}

	$deps = array(
		'jquery',
		$prefix . 'cm',
		$prefix . 'cm-mode-' . $args['type'],
		$prefix . 'cm-lint-' . $args['type'],
		$prefix . 'cm-placeholder',
	);
	$data = array(
		'fieldId'    => 'cce_file_editor',
		'codeMirror' => array(
			'mode'           => $args['type'],
			'lineNumbers'    => true,
			'indentUnit'     => 4,
			'tabSize'        => 4,
			'indentWithTabs' => true,
			'lineWrapping'   => true,

			// Addons
			'gutters'        => array( 'CodeMirror-lint-markers' ),
			'lint'           => true,
		),
	);

	wp_enqueue_script( 'cce-file-editor', plugins_url( 'assets/editor.js', CustomCodeEditor\BASEFILE ), $deps, '20140710', true );
	wp_localize_script( 'cce-file-editor', 'cceFileEditor', $data );
}

/**
 * Enqueue styles for the editor
 */
function enqueue_styles() {
	$prefix = 'cce-file-editor-';
	wp_enqueue_style( $prefix . 'cm', plugins_url( 'assets/codemirror/lib/codemirror.css', CustomCodeEditor\BASEFILE ), array(), '4.3' );
	wp_enqueue_style( $prefix . 'cm-lint', plugins_url( 'assets/codemirror/addon/lint/lint.css', CustomCodeEditor\BASEFILE ), array(), '4.3' );
	wp_enqueue_style( $prefix . 'cm-theme-monokai', plugins_url( 'assets/codemirror/theme/monokai.css', CustomCodeEditor\BASEFILE ), array(), '4.3' );

	wp_enqueue_style( 'cce-file-editor', plugins_url( 'assets/editor.css', CustomCodeEditor\BASEFILE ), array(), '20140710' );
}

/**
 * Check whether the post should be considered "empty"
 *
 * @param boolean $maybe_empty Should we consider this empty?
 * @param array $postarr Post data submitted to {@see wp_insert_post}
 * @return boolean Whether we consider this empty.
 */
function is_empty_post( $maybe_empty, $postarr ) {
	if ( $postarr['post_type'] !== 'cce_css' && $postarr['post_type'] !== 'cce_js' ) {
		return $maybe_empty;
	}

	// Ignore when fetching the default post
	// (WP includes no native way to detect this, so we hack around it by
	// checking the call hierarchy)
	$trace = wp_debug_backtrace_summary( null, 0, false );
	if ( in_array( 'get_default_post_to_edit', $trace ) ) {
		return $maybe_empty;
	}

	return $maybe_empty || empty( $postarr['post_title'] ) || $postarr['post_title'] === __( 'Auto Draft' );
}

/**
 * Sanitize post data in {@see wp_insert_post}
 *
 * Overrides the post content for the file posts, using our own custom
 * sanitization instead of kses.
 *
 * @param array $data Data to insert
 * @param array $postarr Unsanitised data submitted to {@see wp_insert_post}
 * @return array Corrected data
 */
function sanitize_post_data( $data, $postarr ) {
	if ( $data['post_type'] !== 'cce_css' && $data['post_type'] !== 'cce_js' ) {
		return $data;
	}

	// Reset content to originally submitted content
	// (Undoes the kses-related filtering)
	// (wp_filter_nohtml_kses expects slashed data)
	$content = $postarr['post_content'];

	if ( $data['post_type'] === 'cce_css' ) {
		// Re-sanitize, using CSS syntax
		$content = sanitize_css( $content );
	}

	// Set to sanitized version
	$data['post_content'] = $content;

	return $data;
}

/**
 * Sanitize CSS data
 *
 * Strips HTML from the CSS file. This is just making sure, but there's no real
 * reason we need to do this.
 *
 * @param string $css CSS data
 * @return string Sanitized CSS data
 */
function sanitize_css( $css ) {
	// Based on code from Automattic's Safe CSS
	// (wp_filter_nohtml_kses expects slashed data)
	$css = wp_filter_nohtml_kses( $css );
	$css = str_replace( '&gt;', '>', $css ); // kses replaces lone '>' with &gt;

	// Why both KSES and strip_tags? Because we just added some '>'.
	$css = strip_tags( $css );
	return $css;
}
