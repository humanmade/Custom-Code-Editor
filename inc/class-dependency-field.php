<?php

namespace CustomCodeEditor;

use CMB_Post_Select;

/**
 * Dependency selector field
 *
 * Allows files/posts to depend on other files.
 */
class Dependency_Field extends CMB_Post_Select {
	/**
	 * Constructor
	 */
	public function __construct() {

		$args = func_get_args();
		call_user_func_array( array( 'parent', '__construct' ), $args );

		$defaults = array(
			'post_type' => 'cce_css',
			'exclude_current' => true,
		);

		$this->args = wp_parse_args( $this->args, $defaults );

		// BC with built-in
		$this->args['use_ajax']  = true;
		$this->args['ajax_url']  = admin_url( 'admin-ajax.php' );
		$this->args['query']     = array(); // Handled in Ajax callback
		$this->args['ajax_args'] = wp_parse_args( $this->args['query'] );

	}

	/**
	 * Enqueue scripts for the field
	 */
	public function enqueue_scripts() {
		parent::enqueue_scripts();

		wp_enqueue_script( 'cce_dependency_field', plugins_url( 'assets/dependency-field.js', BASEFILE ), array( 'field-select' ), '20140711' );
	}

	/**
	 * Output inline script
	 */
	public function output_script() {

		parent::output_script();

		?>

		<script type="text/javascript">

			(function($) {

				if ( 'undefined' === typeof( window.cmb_select_fields ) )
					return false;

				// Get options for this field so we can modify it.
				var id = <?php echo json_encode( $this->get_js_id() ); ?>;
				var options = window.cmb_select_fields[id];

				<?php if ( $this->args['multiple'] ) : ?>
					// The multiple setting is required when using ajax (because an input field is used instead of select)
					options.multiple = true;
				<?php endif; ?>

				<?php if ( ! empty( $this->value ) ) : ?>

					options.initSelection = function( element, callback ) {

						var data = [];

						<?php if ( $this->args['multiple'] ) : ?>

							<?php foreach ( (array) $this->value as $post_id ) : ?>
								data.push( <?php echo json_encode( array( 'id' => $post_id, 'text' => html_entity_decode( get_the_title( $post_id ) ) ) ); ?> );
							<?php endforeach; ?>

						<?php else : ?>

							data = <?php echo json_encode( array( 'id' => $this->value, 'text' => html_entity_decode( get_the_title( $this->get_value() ) ) ) ); ?>;

						<?php endif; ?>

						callback( data );

					};

				<?php endif; ?>

				<?php
				$data = array(
					'action'    => 'cce_dependency_select',
					'nonce'     => wp_create_nonce( 'cce_dependency_field' ),
					'post_type' => $this->args['post_type'],
					'post_id'   => (string) get_the_id(),
				);

				if ( $this->args['exclude_current'] ) {
					$data['exclude_current'] = true;
				}
				?>

				var ajaxData = <?php echo json_encode( $data ) ?>;

				options.ajax = {
					url: <?php echo json_encode( esc_url( $this->args['ajax_url'] ) ); ?>,
					type: 'POST',
					dataType: 'json',
					data: function( term, page ) {
						ajaxData.page = page;
						return ajaxData;
					},
					results : function( results, page ) {
						var postsPerPage = 10;
						var isMore = ( page * postsPerPage ) < results.total;
	            		return { results: results.posts, more: isMore };
					}
				}

			})( jQuery );

		</script>

		<?php
	}

	/**
	 * Save the data into post meta
	 *
	 * CMB_Field doesn't pass the post ID into the parse methods, so we need to
	 * check necessary stuff here.
	 *
	 * Checks for circular dependencies.
	 *
	 * @param int $post_id Post ID
	 * @param array $values Values to save
	 */
	public function save( $post_id, $values ) {
		$sanitized_values = array();

		$parent = wp_is_post_revision( $post_id );

		foreach ( $values as $value ) {
			// Does the value depend on this post?
			$is_circular = $this->depends_on( $value, $post_id );

			if ( ! empty( $parent ) && ! $is_circular ) {
				// Does the value depend on the revision's parent?
				$is_circular = $this->depends_on( $value, $parent );
			}

			if ( ! $is_circular ) {
				$sanitized_values[] = $value;
			}
		}

		return parent::save( $post_id, $sanitized_values );
	}

	/**
	 * Check if a file depends on another
	 *
	 * @param string|int $file_id File to check dependencies of
	 * @param string|int $dep_id
	 * @return boolean True if `$file_id` or one of its dependencies depends on `$dep_id`
	 */
	protected function depends_on( $file_id, $dep_id ) {
		$post = get_post( absint( $file_id ) );
		if ( empty( $file_id ) || empty( $post ) ) {
			return false;
		}

		$file_deps = get_post_meta( $post->ID, $this->id, false );

		// Check if we're a direct dependency
		if ( in_array( $dep_id, $file_deps ) ) {
			return true;
		}

		// Check children
		foreach ( $file_deps as $sub_dep ) {
			if ( $this->depends_on( $sub_dep, $dep_id ) ) {
				return true;
			}
		}

		return false;
	}

}
