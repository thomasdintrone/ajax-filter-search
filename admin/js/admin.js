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

		// A control that depends on another (the image shape on the featured image, the excerpt length on the excerpt, the
		// time on the date) is hidden while its parent is No. It is only hidden: it stays in the form, so its saved value is
		// submitted and applies again when the parent is turned back on.
		var $kept = $( '.afs-kept' );
		var syncNeeds = function () {
			var anyHidden = false;
			$( '[data-afs-needs]' ).each( function () {
				var parent = $( 'input[name="' + this.getAttribute( 'data-afs-needs' ) + '"]:checked' ).val();
				var off = '0' === parent;
				this.hidden = off;
				anyHidden = anyHidden || off;
			} );
			$kept.prop( 'hidden', ! anyHidden );
		};
		$( '#afs-form' ).on( 'change', 'input[type="radio"]', syncNeeds );
		syncNeeds();

		// The side navigation opens the folded Cards card on its way there.
		$( '.afs-admin__nav a' ).on( 'click', function () {
			$( $( this ).attr( 'href' ) ).find( 'details.afs-fold' ).prop( 'open', true );
		} );

		// The "What's new" card: "Explore" takes you to the design picker, and dismissing sends a background request (the
		// buttons are ordinary form buttons, so without JavaScript the same dismissal is a normal page load).
		$( '[data-afs-explore]' ).on( 'click', function () {
			$( $( this ).attr( 'href' ) ).attr( 'tabindex', '-1' ).trigger( 'focus' );
		} );
		var news = document.getElementById( 'afs-news' );
		var newsForm = document.getElementById( 'afs-news-form' );
		if ( news && newsForm && window.fetch ) {
			newsForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var body = new FormData( newsForm );
				body.append( 'afs_ajax', '1' );
				// The form has a field named "action", which shadows form.action, so the address is read from the attribute.
				fetch( newsForm.getAttribute( 'action' ), { method: 'POST', body: body, credentials: 'same-origin' } )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'HTTP ' + response.status );
						}
						news.remove();
						var status = document.getElementById( 'afs-news-status' );
						if ( status ) {
							status.textContent = data.dismissed || 'Announcement dismissed.';
						}
						var title = document.getElementById( 'afs-admin-title' );
						if ( title ) {
							title.focus();
						}
					} )
					.catch( function () {
						newsForm.submit(); // Fall back to a normal page load; the dismissal is recorded either way.
					} );
			} );
		}

		// Say so when there are unsaved changes.
		var $note = $( '.afs-savebar__note' );
		$( '#afs-form' ).on( 'input change', function () {
			$note.addClass( 'is-dirty' ).text( data.unsaved || 'You have unsaved changes.' );
		} );
	} );
}( jQuery ) );
