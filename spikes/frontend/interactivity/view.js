( window.__afsOrder = window.__afsOrder || [] ).push( 'core' );
import * as I from '@wordpress/interactivity';

const { store, getContext, getElement } = I;
// WordPress 6.8+ wants handlers that touch the event synchronously to be wrapped; older versions have no wrapper.
const sync = I.withSyncEvent || ( ( fn ) => fn );

const KEYS = [ 'afs_s', 'afs_term', 'afs_month', 'afs_year', 'afs_page', 'afs_layout' ];
const timers = new WeakMap();
const controllers = new WeakMap();

const rootOf = ( el ) => el.closest( '.afs[data-afs-config]' );
const field = ( form, name ) => ( form.elements[ name ] ? form.elements[ name ].value : '' );
const layoutOf = ( root ) => ( root.classList.contains( 'afs-layout-grid' ) ? 'grid' : 'list' );

function cleanUrl() {
	const url = new URL( window.location.href );
	KEYS.forEach( ( k ) => url.searchParams.delete( k ) );
	return url;
}

function stateOf( root, page ) {
	const form = root.querySelector( '.afs-form' );
	return { s: field( form, 'afs_s' ), term: field( form, 'afs_term' ), month: field( form, 'afs_month' ), year: field( form, 'afs_year' ), page, layout: layoutOf( root ) };
}

function pageUrl( root, state ) {
	const config = JSON.parse( root.getAttribute( 'data-afs-config' ) || '{}' );
	const url = cleanUrl();
	if ( state.s ) url.searchParams.set( 'afs_s', state.s );
	if ( state.term ) url.searchParams.set( 'afs_term', state.term );
	if ( state.month ) url.searchParams.set( 'afs_month', state.month );
	if ( state.year ) url.searchParams.set( 'afs_year', state.year );
	if ( state.page > 1 ) url.searchParams.set( 'afs_page', state.page );
	if ( state.layout !== ( config.layout || 'list' ) ) url.searchParams.set( 'afs_layout', state.layout );
	return url;
}

function markTabs( root ) {
	const term = field( root.querySelector( '.afs-form' ), 'afs_term' );
	root.querySelectorAll( '.afs-Tabs a[data-afs-term]' ).forEach( ( a ) => {
		const on = a.getAttribute( 'data-afs-term' ) === term;
		a.parentNode.classList.toggle( 'active', on );
		on ? a.setAttribute( 'aria-current', 'true' ) : a.removeAttribute( 'aria-current' );
	} );
}

function apply( root, values ) {
	const form = root.querySelector( '.afs-form' );
	[ [ 's', 'afs_s' ], [ 'term', 'afs_term' ], [ 'month', 'afs_month' ], [ 'year', 'afs_year' ] ].forEach( ( [ k, n ] ) => {
		if ( k in values && form.elements[ n ] ) form.elements[ n ].value = values[ k ];
	} );
	markTabs( root );
}

function setLayout( root, layout, push ) {
	layout = layout === 'grid' ? 'grid' : 'list';
	root.classList.remove( 'afs-layout-list', 'afs-layout-grid' );
	root.classList.add( 'afs-layout-' + layout );
	root.querySelectorAll( '[data-afs-layout]' ).forEach( ( b ) => b.setAttribute( 'aria-pressed', b.getAttribute( 'data-afs-layout' ) === layout ? 'true' : 'false' ) );
	if ( push && root.getAttribute( 'data-afs-url' ) === '1' ) {
		history.replaceState( null, '', pageUrl( root, stateOf( root, getContext().page ) ).href );
	}
}

store( 'afsSpike', {
	callbacks: {
		init() {
			const { ref } = getElement();
			const ctx = getContext();
			const current = ref.querySelector( '.afs-pagination [aria-current="page"]' );
			ctx.page = current ? parseInt( current.getAttribute( 'data-afs-page' ), 10 ) || 1 : 1;
			ref.classList.add( 'afs-js' );
			if ( ref.getAttribute( 'data-afs-url' ) === '1' ) {
				window.addEventListener( 'popstate', () => {
					const params = new URL( window.location.href ).searchParams;
					apply( ref, { s: params.get( 'afs_s' ) || '', term: params.get( 'afs_term' ) || '', month: params.get( 'afs_month' ) || '', year: params.get( 'afs_year' ) || '' } );
					ctx.page = parseInt( params.get( 'afs_page' ), 10 ) || 1;
					setLayout( ref, params.get( 'afs_layout' ) || JSON.parse( ref.getAttribute( 'data-afs-config' ) || '{}' ).layout || 'list', false );
					// Fragment mode reloads; router mode is driven by the router's own popstate handling.
					if ( ! store( 'afsSpike' ).state.router ) store( 'afsSpike' ).actions.load( ref, ctx, false, false );
				} );
			}
		},
		// Extension point a Pro add-on may override (also tested in the load-order check).
		afterResults() {},
	},
	state: {
		get proActive() {
			return false;
		},
	},
	actions: {
		click: sync( ( event ) => {
			const t = event.target;
			const root = rootOf( t );
			const ctx = getContext();
			const router = store( 'afsSpike' ).state.router;
			const tab = t.closest( '.afs-Tabs a[data-afs-term]' );
			const pageLink = t.closest( 'a[data-afs-page]' );
			if ( tab ) {
				event.preventDefault();
				apply( root, { term: tab.getAttribute( 'data-afs-term' ) } );
				ctx.page = 1;
				return store( 'afsSpike' ).actions.load( root, ctx, true, false );
			}
			if ( pageLink && root.querySelector( '.afs-results' ).contains( pageLink ) ) {
				event.preventDefault();
				ctx.page = parseInt( pageLink.getAttribute( 'data-afs-page' ), 10 ) || 1;
				return store( 'afsSpike' ).actions.load( root, ctx, true, true );
			}
			const layout = t.closest( '[data-afs-layout]' );
			if ( layout ) {
				return setLayout( root, layout.getAttribute( 'data-afs-layout' ), true );
			}
			if ( t.closest( '.afs-reset' ) ) {
				apply( root, { s: '', term: '', month: '', year: '' } );
				ctx.page = 1;
				return store( 'afsSpike' ).actions.load( root, ctx, true, false );
			}
			const toggle = t.closest( '.afs-summary-toggle' );
			if ( toggle ) {
				const panel = root.querySelector( '#' + toggle.getAttribute( 'aria-controls' ) );
				const open = toggle.getAttribute( 'aria-expanded' ) === 'true';
				toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				if ( panel ) panel.hidden = open;
			}
			return undefined;
		} ),
		submit: sync( ( event ) => {
			event.preventDefault();
			const root = rootOf( event.target );
			const ctx = getContext();
			ctx.page = 1;
			return store( 'afsSpike' ).actions.load( root, ctx, true, false );
		} ),
		input: sync( ( event ) => {
			if ( event.target.name !== 'afs_s' ) return;
			const root = rootOf( event.target );
			const ctx = getContext();
			clearTimeout( timers.get( root ) );
			timers.set( root, setTimeout( () => {
				ctx.page = 1;
				store( 'afsSpike' ).actions.load( root, ctx, true, false );
			}, 400 ) );
		} ),
		change: sync( ( event ) => {
			if ( event.target.name !== 'afs_month' && event.target.name !== 'afs_year' ) return;
			const root = rootOf( event.target );
			const ctx = getContext();
			ctx.page = 1;
			store( 'afsSpike' ).actions.load( root, ctx, true, false );
		} ),
		*load( root, ctx, push, scroll ) {
			const { state } = store( 'afsSpike' );
			const st = stateOf( root, ctx.page );
			const syncs = root.getAttribute( 'data-afs-url' ) === '1';

			if ( state.router ) {
				// Router arm: fetch the whole page and swap the data-wp-router-region elements.
				yield import( '@wordpress/interactivity-router' );
				const { actions } = store( 'core/router' );
				yield actions.navigate( pageUrl( root, st ).href, { replace: ! push } );
				return;
			}

			const config = JSON.parse( root.getAttribute( 'data-afs-config' ) || '{}' );
			const url = new URL( state.restUrl, window.location.href );
			Object.keys( config ).forEach( ( k ) => {
				if ( k !== 'layout' && config[ k ] !== '' ) url.searchParams.set( k, config[ k ] );
			} );
			url.searchParams.set( 'afs_s', st.s );
			url.searchParams.set( 'afs_term', st.term );
			url.searchParams.set( 'afs_month', st.month || 0 );
			url.searchParams.set( 'afs_year', st.year || 0 );
			url.searchParams.set( 'afs_page', st.page );
			url.searchParams.set( 'base_url', cleanUrl().href );
			url.searchParams.set( 'instance', root.getAttribute( 'data-afs-instance' ) || 1 );

			controllers.get( root )?.abort();
			const controller = new AbortController();
			controllers.set( root, controller );
			ctx.busy = true;
			const results = root.querySelector( '.afs-results' );
			try {
				const response = yield fetch( url.href, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal } );
				if ( ! response.ok ) throw new Error( 'HTTP ' + response.status );
				const data = yield response.json();
				results.innerHTML = data.html;
				markTabs( root );
				ctx.busy = false;
				if ( syncs ) {
					const next = pageUrl( root, st ).href;
					if ( next !== window.location.href ) history[ push ? 'pushState' : 'replaceState' ]( null, '', next );
				}
				if ( scroll && root.getBoundingClientRect().top < 0 ) root.scrollIntoView( { block: 'start' } );
				store( 'afsSpike' ).callbacks.afterResults( root, data );
			} catch ( error ) {
				if ( error && error.name === 'AbortError' ) return;
				const p = document.createElement( 'p' );
				p.className = 'afs-error';
				p.setAttribute( 'role', 'alert' );
				p.textContent = state.error;
				results.replaceChildren( p );
				ctx.busy = false;
			}
		},
	},
} );
