import { test, expect } from '@playwright/test';
import { feed, titles, serverTitles, settled } from './helpers.mjs';

const REST = '**/wp-json/afs/v1/results*';

test.describe( 'front-end extension points', () => {
	test( 'an add-on loaded before the script gets loaded, before-request and results, plus DOM events', async ( { page } ) => {
		await page.addInitScript( () => {
			window.__log = [];
			window.afsRegistry = window.afsRegistry || { q: [] };
			window.afsRegistry.q.push( ( api ) => {
				window.__log.push( 'api v' + api.version );
				api.on( 'loaded', ( e ) => window.__log.push( 'loaded ' + e.root.getAttribute( 'data-afs-instance' ) ) );
				api.on( 'before-request', ( e ) => e.url.searchParams.set( 'x_addon', '1' ) );
				api.on( 'results', ( e ) => window.__log.push( 'results ' + e.data.meta.found + ' ' + e.state.term ) );
			} );
			document.addEventListener( 'afs:results', ( e ) => window.__log.push( 'dom ' + e.detail.data.found ) );
		} );
		await page.goto( '/afs-double/' );
		await settled( page );
		const requested = page.waitForRequest( ( r ) => r.url().includes( '/wp-json/afs/v1/results' ) );
		await feed( page ).locator( '.afs-Tabs a[data-afs-term="news"]' ).click();
		expect( ( await requested ).url() ).toContain( 'x_addon=1' );
		await settled( page );
		const log = await page.evaluate( () => window.__log );
		expect( log[ 0 ] ).toBe( 'api v1' );
		expect( log ).toEqual( expect.arrayContaining( [ 'loaded 1', 'loaded 2' ] ) );
		expect( log.some( ( l ) => /^results \d+ news$/.test( l ) ) ).toBe( true );
		expect( log.some( ( l ) => /^dom \d+$/.test( l ) ) ).toBe( true );
	} );

	test( 'an add-on registered after the script still receives later events', async ( { page } ) => {
		await page.goto( '/afs-single/' );
		await settled( page );
		await page.evaluate( () => {
			window.__seen = 0;
			window.afsRegistry.use( ( api ) => api.on( 'results', () => { window.__seen++; } ) );
		} );
		await feed( page ).locator( '.afs-Tabs a[data-afs-term="events"]' ).click();
		await settled( page );
		expect( await page.evaluate( () => window.__seen ) ).toBe( 1 );
	} );

	test( 'a hook that throws is ignored and the feed keeps working', async ( { page, request } ) => {
		await page.addInitScript( () => {
			// Browsers print a logged Error object differently (Firefox gives just "Error Error"), so collect the message ourselves.
			window.__errs = [];
			const original = console.error;
			console.error = ( ...args ) => {
				window.__errs.push( args.map( ( a ) => ( a && a.message ? a.message : String( a ) ) ).join( ' ' ) );
				original.apply( console, args );
			};
			window.afsRegistry = { q: [ ( api ) => { api.on( 'results', () => { throw new Error( 'broken add-on' ); } ); api.on( 'before-request', () => { throw new Error( 'broken add-on 2' ); } ); } ] };
		} );
		await page.goto( '/afs-single/' );
		await settled( page );
		await feed( page ).locator( '.afs-Tabs a[data-afs-term="news"]' ).click();
		await settled( page );
		expect( page.url() ).toContain( 'afs_term=news' );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		expect( ( await page.evaluate( () => window.__errs ) ).join( ' ' ) ).toContain( 'broken add-on' );
	} );
} );

test.describe( 'graceful fallback when the request cannot complete', () => {
	for ( const [ name, handler ] of [
		[ 'network failure', ( route ) => route.abort() ],
		[ 'server error', ( route ) => route.fulfill( { status: 500, body: 'nope' } ) ],
		[ 'a body that is not JSON', ( route ) => route.fulfill( { status: 200, contentType: 'application/json', body: '<html>' } ) ],
	] ) {
		test( `the first feed falls back to a normal page load: ${ name }`, async ( { page, request } ) => {
			await page.goto( '/afs-single/' );
			await settled( page );
			await page.route( REST, handler );
			await feed( page ).locator( '.afs-Tabs a[data-afs-term="press-releases"]' ).click();
			await page.waitForURL( /afs_term=press-releases/ );
			await page.waitForLoadState( 'load' );
			expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
			expect( ( await titles( page ) ).length ).toBeGreaterThan( 0 );
		} );
	}

	test( 'the first feed keeps its search text and page when it falls back', async ( { page } ) => {
		await page.goto( '/afs-single/' );
		await settled( page );
		await page.route( REST, ( route ) => route.abort() );
		await feed( page ).locator( 'input[name="afs_s"]' ).fill( 'Quarterly' );
		await page.keyboard.press( 'Enter' ); // The Update button is hidden when JavaScript runs.
		await page.waitForURL( /afs_s=Quarterly/ );
		await expect( feed( page ).locator( 'input[name="afs_s"]' ) ).toHaveValue( 'Quarterly' );
	} );

	test( 'a feed that does not own the address bar shows a message instead, and recovers', async ( { page } ) => {
		await page.goto( '/afs-double/' );
		await settled( page );
		await page.route( REST, ( route ) => route.abort() );
		await feed( page, 2 ).locator( '.afs-Tabs a[data-afs-term="events"]' ).click();
		await expect( feed( page, 2 ).locator( '.afs-error[role="alert"]' ) ).toBeVisible();
		expect( new URL( page.url() ).search ).toBe( '' );
		await page.unroute( REST );
		await feed( page, 2 ).locator( '.afs-Tabs a[data-afs-term="news"]' ).click();
		await settled( page, 2 );
		await expect( feed( page, 2 ).locator( '.afs-error' ) ).toHaveCount( 0 );
		expect( ( await titles( page, 2 ) ).length ).toBeGreaterThan( 0 );
	} );
} );
