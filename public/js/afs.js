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
	var URL_KEYS = [ 'afs_s', 'afs_term', 'afs_month', 'afs_year', 'afs_page', 'afs_layout', 'afs_sort' ];
	var SEARCH_DELAY = 400;

	/**
	 * Extension points for add-ons. An add-on can load before or after this script:
	 *
	 *   ( window.afsRegistry = window.afsRegistry || { q: [] } );
	 *   var use = function ( api ) { api.on( 'results', function ( e ) { ... } ); };
	 *   window.afsRegistry.use ? window.afsRegistry.use( use ) : window.afsRegistry.q.push( use );
	 *
	 * Hooks: 'loaded' ( { root, config } ), 'before-request' ( { url, state, root }, may change url.searchParams ),
	 * 'results' ( { root, data, state } ), 'error' ( { root, error } ). The same events (except before-request) are
	 * also dispatched on the feed element as DOM events: afs:loaded, afs:results, afs:error (bubbling, e.detail).
	 * A hook that throws is logged and ignored.
	 */
	var hooks = {};
	var api = {
		version: 1,
		on: function ( name, fn ) {
			( hooks[ name ] = hooks[ name ] || [] ).push( fn );
		},
	};
	var registry = ( window.afsRegistry = window.afsRegistry || { q: [] } );
	registry.api = api;
	registry.use = function ( fn ) {
		try {
			fn( api );
		} catch ( e ) {
			log( e );
		}
	};
	( registry.q || [] ).forEach( registry.use );
	registry.q = [];

	function log( e ) {
		if ( window.console && window.console.error ) {
			window.console.error( e );
		}
	}

	function run( name, payload ) {
		( hooks[ name ] || [] ).forEach( function ( fn ) {
			try {
				fn( payload );
			} catch ( e ) {
				log( e );
			}
		} );
	}

	function emit( name, payload, announce ) {
		run( name, payload );
		if ( announce && typeof window.CustomEvent === 'function' ) {
			payload.root.dispatchEvent( new window.CustomEvent( 'afs:' + name, { bubbles: true, detail: payload } ) );
		}
	}

	function debounce( fn, wait ) {
		var timer;
		var debounced = function () {
			var args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( null, args );
			}, wait );
		};
		debounced.cancel = function () {
			clearTimeout( timer );
		};
		return debounced;
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
		emit( 'loaded', { root: root, config: this.config }, true );
	}

	Widget.prototype.state = function () {
		return {
			s: this.read( 'afs_s' ),
			term: this.read( 'afs_term' ),
			month: this.read( 'afs_month' ),
			year: this.read( 'afs_year' ),
			page: this.page,
			layout: this.layout(),
			sort: this.read( 'afs_sort' ),
		};
	};

	/** The sort the feed starts with (the select's own default), or '' when the control is not shown. */
	Widget.prototype.defaultSort = function () {
		var select = this.form.elements.afs_sort;
		return select ? select.getAttribute( 'data-default' ) || '' : '';
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
			// Kept so that any other load can cancel a pending one (see load()).
			this.searchTimer = debounce( function () {
				self.page = 1;
				self.load( true );
			}, SEARCH_DELAY );
			search.addEventListener( 'input', this.searchTimer );
		}

		[ 'afs_month', 'afs_year', 'afs_sort' ].forEach( function ( name ) {
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
			// A filter chip, Clear all, or the recovery link in an empty result: plain links that also work without JavaScript.
			var clear = event.target.closest( 'a[data-afs-clear]' );
			if ( clear && self.root.contains( clear ) ) {
				event.preventDefault();
				var key = clear.getAttribute( 'data-afs-clear' );
				self.apply( key === 'all' ? { s: '', term: '', month: '', year: '' } : ( function ( v ) { v[ key ] = ''; return v; }( {} ) ) );
				self.page = 1;
				self.load( true );
				return;
			}

			if ( event.target.closest( '.afs-retry' ) ) {
				event.preventDefault();
				self.load( false );
				return;
			}

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

			// 1.x template overrides use <a class="showSummary"> and a .afs-Hidden panel.
			var legacy = event.target.closest( 'a.showSummary' );
			if ( legacy ) {
				event.preventDefault();
				var tools = legacy.closest( '.afs-PRTools' );
				var legacyPanel = tools ? tools.nextElementSibling : null;
				if ( legacyPanel ) {
					legacyPanel.style.display = legacyPanel.style.display === 'block' ? 'none' : 'block';
				}
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
		[ [ 's', 'afs_s' ], [ 'term', 'afs_term' ], [ 'month', 'afs_month' ], [ 'year', 'afs_year' ], [ 'sort', 'afs_sort' ] ].forEach( function ( pair ) {
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
			sort: params.get( 'afs_sort' ) || this.defaultSort(),
		} );
		this.page = parseInt( params.get( 'afs_page' ), 10 ) || 1;
		this.setLayout( params.get( 'afs_layout' ) || this.config.layout || 'list' );
	};

	/** The page URL that shows the current state (what writeUrl puts in the address bar). */
	Widget.prototype.urlFor = function ( state ) {
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
		if ( state.sort && state.sort !== this.defaultSort() ) {
			url.searchParams.set( 'afs_sort', state.sort );
		}
		return url;
	};

	Widget.prototype.writeUrl = function ( push ) {
		var url = this.urlFor( this.state() );
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
		// A load that is already under way (Enter, a tab, a page link) supersedes a pending debounced search; left alone,
		// it would fire 400 ms later, repeat the request and send the visitor back to page 1.
		if ( this.searchTimer ) {
			this.searchTimer.cancel();
		}
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
		if ( state.sort && state.sort !== this.defaultSort() ) {
			url.searchParams.set( 'afs_sort', state.sort );
		}
		url.searchParams.set( 'base_url', cleanUrl().href );
		url.searchParams.set( 'instance', this.root.getAttribute( 'data-afs-instance' ) || 1 );
		run( 'before-request', { url: url, state: state, root: this.root } );

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
				emit( 'results', { root: self.root, data: data, state: state }, true );
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
				emit( 'error', { root: self.root, error: error }, true );
				// The server renders every state, so when the request cannot be completed the visitor still gets
				// their results with a normal page load. Only the feed that owns the address bar can do that;
				// any other feed shows a message instead.
				if ( self.syncsUrl ) {
					window.location.assign( self.urlFor( state ).href );
					return;
				}
				var message = document.createElement( 'p' );
				message.className = 'afs-error';
				message.setAttribute( 'role', 'alert' );
				message.textContent = ( ( settings.i18n && settings.i18n.error ) || 'Error' ) + ' ';
				var retry = document.createElement( 'button' );
				retry.type = 'button';
				retry.className = 'afs-btn afs-retry';
				retry.textContent = ( settings.i18n && settings.i18n.retry ) || 'Retry';
				message.appendChild( retry );
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
