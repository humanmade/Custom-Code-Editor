jQuery( document ).ready( function ( $ ) {
	var options = [];
	var nameToValue = [];

	$( '.cmb2-repeatable-autocomplete' ).each( function ( i, el ) {
		if ( typeof $( this ).data( 'ui-autocomplete' ) !== 'undefined' ) {
			return;
		}

		$( this ).autocomplete( {
			source: function ( request, response ) {
				$.ajax( {
					url: ajaxurl,
					data: {
						action: 'get_post_options',
						q: request.term,
						post_types: cceAutocomplete.post_types,
						exclude: cceAutocomplete.curPost,
					},
					success: function ( data ) {

						console.warn( data );

						// Set up options and name to value for this returned set.
						var values = data.data;
						options = [];
						nameToValue = [];

						for ( optionI in values ) {
							var option = values[ optionI ];
							options.push( option.name );
							nameToValue[ option.name ] = option.value;
						}

						response( options );
					}
				} );
			}
		} );

		// Also set up a blur function to update the ID.
		$( 'this' ).blur( function ( e ) {
			$( this ).prev( 'input' ).val( nameToValue[ $( this ).val() ] );
		} );
	} );
} );
