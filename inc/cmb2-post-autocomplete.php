<?php
/**
 * CMB2 post autocomplete field.
 *
 * @package
 */

namespace CustomCodeEditor\CMB2_Post_Autocomplete;

use CustomCodeEditor;
use WP_Query;

function load() {
	add_action( 'cmb2_render_post_autocomplete', __NAMESPACE__ . '\\render_post_autocomplete', 10, 5 );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_scripts' );
	add_action( 'wp_ajax_get_post_options', __NAMESPACE__ . '\\get_post_autocomplete_options' );
}

/**
 * Gets the jQuery autocomplete widget ready.
 */
function admin_enqueue_scripts() {
	wp_enqueue_script( 'jquery-ui-autocomplete' );
	wp_enqueue_script( 'cce-post-autocomplete', plugins_url( 'assets/cmb2-post-autocomplete.js', CustomCodeEditor\BASEFILE ), [ 'jquery-ui-autocomplete' ], CustomCodeEditor\VERSION, true );
}

/**
 * Gets the post title from the ID for mapping purposes in autocompletes.
 *
 * @param int $id
 * @return string
 */
function get_post_title_from_id( $id ) : string {
	if ( empty( $id ) ) {
		return '';
	}

	$post = get_post( absint( $id ) );

	return $post->post_title;
}

/**
 * Renders the autocomplete type
 *
 * @param CMB2_Field  $field_object
 * @param string      $escaped_value     The value of this field passed through the escaping filter. It defaults to sanitize_text_field. If you need the unescaped value, you can access it via $field_type_object->value().
 * @param string      $object_id         The id of the object you are working with. Most commonly, the post id.
 * @param string      $object_type       The type of object you are workingwith. Most commonly, post (this applies to all post-types), but could also be comment, user or options-page.
 * @param CMB2_Object $field_type_object This is an instance of the CMB2 object and gives you access to all of the methods that CMB2 uses to build its field types.
 */
function render_post_autocomplete( $field_object, $escaped_value, $object_id, $object_type, $field_type_object ) {

	// Store the value in a hidden field.
	echo $field_type_object->hidden();

	$repeatable_class = 'cmb2-repeatable-autocomplete';

	// Set up the options or source PHP variables.
	if ( empty( $options ) ) {
		$value = get_post_title_from_id( $field_object->escaped_value );
	} else {

		// Set the value.
		if ( empty( $field_object->escaped_value ) ) {
			$value = '';
		} else {
			foreach ( $options as $option ) {
				if ( $option['value'] == $field_object->escaped_value ) {
					$value = $option['name'];
					break;
				}
			}
		}
	}

	$post_id = $_GET['post'];

	// Now, set up the script.
	wp_localize_script(
		'cce-post-autocomplete',
		'cceAutocomplete',
		[
			'post_types' => $field_object->args['post_type'] ?? CustomCodeEditor\get_used_post_types(),
			'curPost'    => $post_id,
		]
	);
	?>

	<input
		size="50"
		value="<?php echo esc_attr( htmlspecialchars( $value ) ); ?>"
		class="<?php echo esc_attr( $repeatable_class ); ?> "
	/>
	<?php
}

/**
 * Gets the post options in JSON format for the autocomplete
 */
function get_post_autocomplete_options() {
	// @todo:: authorize somehow?

	$query_args = [
		's'              => sanitize_text_field( $_GET['q'] ),
		'post_type'      => $_GET['post_types'],// @todo:: sanitize,
		'post__not_in'   => [ absint( $_GET['exclude'] ) ],
		'posts_per_page' => 50,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'orderby'        => 'post_title',
	];

	$query    = new WP_Query( $query_args );

	$response = array_map( function( $post_id ) {
		return [
			'name'  => get_the_title( $post_id ),
			'value' => $post_id
		];
	}, $query->posts );

	wp_send_json_success( $response );
}
