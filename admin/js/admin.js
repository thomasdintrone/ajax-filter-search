/**
 * Settings screen: keep the taxonomy list in step with the chosen post type,
 * and activate the color picker.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $postType = $( 'select.general-post-type' );
		var $taxonomy = $( 'select.general-post-taxonomy' );
		var data = window.afsAdmin || { taxonomies: {}, none: 'None' };

		$postType.on( 'change', function () {
			var current = $taxonomy.val();
			var options = data.taxonomies[ this.value ] || {};

			$taxonomy.empty().append( $( '<option>', { value: 'none', text: data.none } ) );
			Object.keys( options ).forEach( function ( name ) {
				$taxonomy.append( $( '<option>', { value: name, text: options[ name ] } ) );
			} );
			$taxonomy.val( current );
			if ( ! $taxonomy.val() ) {
				$taxonomy.val( 'none' );
			}
		} );

		$( '.plugin-colorpicker' ).wpColorPicker();
	} );
}( jQuery ) );
