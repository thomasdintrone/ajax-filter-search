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

		// Copy buttons for the example shortcodes.
		$( '.afs-copy__btn' ).on( 'click', function () {
			var button = this;
			var text = button.getAttribute( 'data-afs-copy' );
			var done = function () {
				var label = button.textContent;
				button.textContent = data.copied || 'Copied';
				button.classList.add( 'is-copied' );
				setTimeout( function () {
					button.textContent = label;
					button.classList.remove( 'is-copied' );
				}, 1600 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done );
				return;
			}
			var area = document.createElement( 'textarea' );
			area.value = text;
			document.body.appendChild( area );
			area.select();
			try {
				document.execCommand( 'copy' );
				done();
			} catch ( error ) { // eslint-disable-line no-unused-vars
				// Nothing to do: the shortcode is visible and can be selected by hand.
			}
			document.body.removeChild( area );
		} );

		// Highlight the section in view in the side navigation.
		var links = $( '.afs-admin__nav a' );
		if ( 'IntersectionObserver' in window && links.length ) {
			var observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						links.removeClass( 'is-current' ).filter( '[href="#' + entry.target.id + '"]' ).addClass( 'is-current' );
					}
				} );
			}, { rootMargin: '-20% 0px -65% 0px' } );
			$( '.afs-card' ).each( function () {
				observer.observe( this );
			} );
			links.first().addClass( 'is-current' );
		}

		// Say so when there are unsaved changes.
		var $note = $( '.afs-savebar__note' );
		$( '#afs-form' ).on( 'input change', function () {
			$note.addClass( 'is-dirty' ).text( data.unsaved || 'You have unsaved changes.' );
		} );
	} );
}( jQuery ) );
