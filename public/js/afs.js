/**
 * Ajax Filter Search front end. No dependencies.
 *
 * The server renders every widget fully, so this script only enhances it:
 * filters and pagination fetch a results fragment from the REST endpoint,
 * and the first widget on a page keeps its state in the address bar.
 */
( function () {
	'use strict';

	var settings = window.afsSettings || {};
	var URL_KEYS = [ 'afs_s', 'afs_term', 'afs_month', 'afs_year', 'afs_page', 'afs_layout' ];
	var SEARCH_DELAY = 400;

	function debounce( fn, wait ) {
		var timer;
		return function () {
			var args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( null, args );
			}, wait );
		};
	}

	/** Current page URL without this plugin's parameters. */
	function cleanUrl( href ) {
		var url = new URL( href || window.location.href );
		URL_KEYS.forEach( function ( key ) {
			url.searchParams.delete( key );
		} );
		return url;
	}

	function Widget( root ) {
		this.root = root;
		this.form = root.querySelector( '.afs-form' );
		this.results = root.querySelector( '.afs-results' );
		this.config = JSON.parse( root.getAttribute( 'data-afs-config' ) || '{}' );
		this.syncsUrl = root.getAttribute( 'data-afs-url' ) === '1';
		this.page = 1;
		this.controller = null;

		this.read = function ( name ) {
			var el = this.form.elements[ name ];
			return el ? el.value : '';
		};

		this.layout = function () {
			return root.classList.contains( 'afs-layout-grid' ) ? 'grid' : 'list';
		};

		/** Page number the server rendered, taken from the current-page link. */
		var current = root.querySelector( '.afs-pagination [aria-current="page"]' );
		if ( current ) {
			this.page = parseInt( current.getAttribute( 'data-afs-page' ), 10 ) || 1;
		}

		this.bind();
		root.classList.add( 'afs-js' );
	}

	Widget.prototype.state = function () {
		return {
			s: this.read( 'afs_s' ),
			term: this.read( 'afs_term' ),
			month: this.read( 'afs_month' ),
			year: this.read( 'afs_year' ),
			page: this.page,
			layout: this.layout(),
		};
	};

	Widget.prototype.bind = function () {
		var self = this;

		this.form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			self.page = 1;
			self.load( true );
		} );

		var search = this.form.elements.afs_s;
		if ( search ) {
			search.addEventListener(
				'input',
				debounce( function () {
					self.page = 1;
					self.load( true );
				}, SEARCH_DELAY )
			);
		}

		[ 'afs_month', 'afs_year' ].forEach( function ( name ) {
			var el = self.form.elements[ name ];
			if ( el ) {
				el.addEventListener( 'change', function () {
					self.page = 1;
					self.load( true );
				} );
			}
		} );

		var reset = this.form.querySelector( '.afs-reset' );
		if ( reset ) {
			reset.addEventListener( 'click', function () {
				self.apply( { s: '', term: '', month: '', year: '' } );
				self.page = 1;
				self.load( true );
			} );
		}

		// Category tabs.
		this.root.addEventListener( 'click', function ( event ) {
			var tab = event.target.closest( '.afs-Tabs a[data-afs-term]' );
			if ( tab ) {
				event.preventDefault();
				self.apply( { term: tab.getAttribute( 'data-afs-term' ) } );
				self.page = 1;
				self.load( true );
				return;
			}

			var pageLink = event.target.closest( 'a[data-afs-page]' );
			if ( pageLink && self.results.contains( pageLink ) ) {
				event.preventDefault();
				self.page = parseInt( pageLink.getAttribute( 'data-afs-page' ), 10 ) || 1;
				self.load( true, true );
				return;
			}

			var layoutBtn = event.target.closest( '[data-afs-layout]' );
			if ( layoutBtn ) {
				self.setLayout( layoutBtn.getAttribute( 'data-afs-layout' ) );
				return;
			}

			var toggle = event.target.closest( '.afs-summary-toggle' );
			if ( toggle ) {
				var panel = self.root.querySelector( '#' + toggle.getAttribute( 'aria-controls' ) );
				var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
				toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				if ( panel ) {
					panel.hidden = open;
				}
			}
		} );

		if ( this.syncsUrl ) {
			window.addEventListener( 'popstate', function () {
				self.fromUrl();
				self.load( false );
			} );
		}
	};

	/** Push values into the form controls and tab highlight. */
	Widget.prototype.apply = function ( values ) {
		var form = this.form;
		[ [ 's', 'afs_s' ], [ 'term', 'afs_term' ], [ 'month', 'afs_month' ], [ 'year', 'afs_year' ] ].forEach( function ( pair ) {
			if ( Object.prototype.hasOwnProperty.call( values, pair[ 0 ] ) && form.elements[ pair[ 1 ] ] ) {
				form.elements[ pair[ 1 ] ].value = values[ pair[ 0 ] ];
			}
		} );
		this.markTabs();
	};

	Widget.prototype.markTabs = function () {
		var term = this.read( 'afs_term' );
		this.root.querySelectorAll( '.afs-Tabs a[data-afs-term]' ).forEach( function ( link ) {
			var active = link.getAttribute( 'data-afs-term' ) === term;
			link.parentNode.classList.toggle( 'active', active );
			if ( active ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	};

	Widget.prototype.setLayout = function ( layout ) {
		layout = layout === 'grid' ? 'grid' : 'list';
		this.root.classList.remove( 'afs-layout-list', 'afs-layout-grid' );
		this.root.classList.add( 'afs-layout-' + layout );
		this.root.querySelectorAll( '[data-afs-layout]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', btn.getAttribute( 'data-afs-layout' ) === layout ? 'true' : 'false' );
		} );
		if ( this.syncsUrl ) {
			this.writeUrl( false );
		}
	};

	/** Read state back from the address bar (back/forward buttons). */
	Widget.prototype.fromUrl = function () {
		var params = new URL( window.location.href ).searchParams;
		this.apply( {
			s: params.get( 'afs_s' ) || '',
			term: params.get( 'afs_term' ) || '',
			month: params.get( 'afs_month' ) || '',
			year: params.get( 'afs_year' ) || '',
		} );
		this.page = parseInt( params.get( 'afs_page' ), 10 ) || 1;
		this.setLayout( params.get( 'afs_layout' ) || this.config.layout || 'list' );
	};

	Widget.prototype.writeUrl = function ( push ) {
		var state = this.state();
		var url = cleanUrl();
		if ( state.s ) {
			url.searchParams.set( 'afs_s', state.s );
		}
		if ( state.term ) {
			url.searchParams.set( 'afs_term', state.term );
		}
		if ( state.month ) {
			url.searchParams.set( 'afs_month', state.month );
		}
		if ( state.year ) {
			url.searchParams.set( 'afs_year', state.year );
		}
		if ( state.page > 1 ) {
			url.searchParams.set( 'afs_page', state.page );
		}
		if ( state.layout !== ( this.config.layout || 'list' ) ) {
			url.searchParams.set( 'afs_layout', state.layout );
		}
		if ( url.href !== window.location.href ) {
			window.history[ push ? 'pushState' : 'replaceState' ]( null, '', url.href );
		}
	};

	/**
	 * Fetch and show results for the current form state.
	 *
	 * @param {boolean} push   Add a history entry for this state.
	 * @param {boolean} scroll Bring the top of the widget into view afterwards.
	 */
	Widget.prototype.load = function ( push, scroll ) {
		var self = this;
		var state = this.state();

		var url = new URL( settings.restUrl, window.location.href );
		Object.keys( this.config ).forEach( function ( key ) {
			if ( key !== 'layout' && self.config[ key ] !== '' ) {
				url.searchParams.set( key, self.config[ key ] );
			}
		} );
		url.searchParams.set( 'afs_s', state.s );
		url.searchParams.set( 'afs_term', state.term );
		url.searchParams.set( 'afs_month', state.month || 0 );
		url.searchParams.set( 'afs_year', state.year || 0 );
		url.searchParams.set( 'afs_page', state.page );
		url.searchParams.set( 'base_url', cleanUrl().href );
		url.searchParams.set( 'instance', this.root.getAttribute( 'data-afs-instance' ) || 1 );

		if ( this.controller ) {
			this.controller.abort();
		}
		this.controller = typeof AbortController === 'function' ? new AbortController() : null;

		this.root.classList.add( 'afs-loading' );
		this.results.setAttribute( 'aria-busy', 'true' );

		fetch( url.href, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
			signal: this.controller ? this.controller.signal : undefined,
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( data ) {
				self.results.innerHTML = data.html;
				self.finish();
				if ( self.syncsUrl ) {
					self.writeUrl( push );
				}
				if ( scroll && self.root.getBoundingClientRect().top < 0 ) {
					self.root.scrollIntoView( { block: 'start' } );
				}
			} )
			.catch( function ( error ) {
				if ( error && error.name === 'AbortError' ) {
					return;
				}
				var message = document.createElement( 'p' );
				message.className = 'afs-error';
				message.setAttribute( 'role', 'alert' );
				message.textContent = ( settings.i18n && settings.i18n.error ) || 'Error';
				self.results.replaceChildren( message );
				self.finish();
			} );
	};

	Widget.prototype.finish = function () {
		this.root.classList.remove( 'afs-loading' );
		this.results.setAttribute( 'aria-busy', 'false' );
	};

	function init() {
		document.querySelectorAll( '.afs[data-afs-config]' ).forEach( function ( root ) {
			if ( ! root.classList.contains( 'afs-js' ) ) {
				new Widget( root );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
