import { test, expect } from '@playwright/test';

// HTTP-level contract checks that used to live only in bin/smoke-test.sh and bin/compat-test.sh (which CI never ran).
// They run against the real stack, so they cover the REST route, the 1.x ajax action and what a visitor receives.
const items = ( html ) => ( html.match( /<article class="[^"]*afs-item/g ) || [] ).length;
const restUrl = ( q ) => `/wp-json/afs/v1/results?${ q }`;
const rest = async ( request, q ) => ( await ( await request.get( restUrl( q ) ) ).json() ).html;
const legacy = ( request, form ) => request.post( '/wp-admin/admin-ajax.php', { form: { action: 'my_action', ...form } } );

test.describe( 'pages', () => {
	test( 'single, two-feed, legacy-attribute and block pages render the expected number of items', async ( { request } ) => {
		const count = async ( p ) => items( await ( await request.get( p ) ).text() );
		expect( await count( '/afs-single/' ) ).toBe( 10 );
		expect( await count( '/afs-double/' ) ).toBe( 8 );
		expect( await count( '/afs-block/' ) ).toBe( 4 );
		const legacyHtml = await ( await request.get( '/afs-legacy/' ) ).text();
		expect( ( legacyHtml.match( /data-afs-term=/g ) || [] ).length ).toBe( 3 ); // All + the two filter_by terms
	} );

	test( 'assets load only on pages with a feed', async ( { request } ) => {
		expect( await ( await request.get( '/' ) ).text() ).not.toContain( 'afs.js' );
		expect( await ( await request.get( '/afs-single/' ) ).text() ).toContain( 'afs.js' );
	} );

	test( 'first feed keeps its 1.x ids once; ajaxurl is defined only on feed pages', async ( { request } ) => {
		const double = await ( await request.get( '/afs-double/' ) ).text();
		for ( const id of [ 'newsForm', 'newsPanelResults', 'updateBtn', 'resetBtn' ] ) {
			expect( ( double.match( new RegExp( `id="${ id }"`, 'g' ) ) || [] ).length, id ).toBe( 1 );
		}
		expect( await ( await request.get( '/afs-single/' ) ).text() ).toContain( 'window.ajaxurl' );
		expect( await ( await request.get( '/' ) ).text() ).not.toContain( 'window.ajaxurl' );
	} );
} );

test.describe( 'REST route', () => {
	test( 'validation rejects out-of-range values with 400', async ( { request } ) => {
		for ( const q of [ 'posts_per_page=9999', 'afs_month=13', 'afs_page=0', 'afs_page=99999999999999999999' ] ) {
			expect( ( await request.get( restUrl( q ) ) ).status(), q ).toBe( 400 );
		}
		expect( ( await request.get( restUrl( 'post_type=post&post_tax=category' ) ) ).status() ).toBe( 200 );
	} );

	test( 'unknown post type falls back, off-site base_url is ignored, search text is escaped', async ( { request } ) => {
		const a = await ( await request.get( restUrl( 'post_type=post' ) ) ).json();
		const b = await ( await request.get( restUrl( 'post_type=revision' ) ) ).json();
		expect( b.found ).toBe( a.found );
		expect( await ( await request.get( restUrl( 'base_url=http://evil.example/x' ) ) ).text() ).not.toContain( 'evil.example' );
		expect( await ( await request.get( restUrl( 'afs_s=%3Cscript%3E' ) ) ).text() ).not.toContain( '<script>' );
	} );

	test( 'never lists drafts, scheduled or private posts', async ( { request } ) => {
		expect( items( await rest( request, 'afs_s=SECRET' ) ) ).toBe( 0 );
	} );
} );

test.describe( 'REST caching (anonymous visitors)', () => {
	test( 'sends meta, a validator and Cache-Control; a matching validator gets an empty 304', async ( { request } ) => {
		const url = restUrl( 'posts_per_page=5&afs_term=news' );
		const first = await request.get( url );
		expect( first.status() ).toBe( 200 );
		const body = await first.json();
		expect( Object.keys( body ).sort() ).toEqual( [ 'found', 'html', 'meta', 'pages' ] );
		expect( body.meta.per_page ).toBe( 5 );
		const etag = first.headers().etag;
		expect( etag ).toMatch( /^"[a-f0-9]{32}"$/ );
		expect( first.headers()[ 'cache-control' ] ).toContain( 'max-age=60' );

		const again = await request.get( url, { headers: { 'If-None-Match': etag } } );
		expect( again.status() ).toBe( 304 );
		expect( ( await again.body() ).length ).toBe( 0 );
		expect( again.headers().etag ).toBe( etag );
	} );

	test( 'a different state gets a different validator', async ( { request } ) => {
		const a = ( await request.get( restUrl( 'posts_per_page=5&afs_term=news' ) ) ).headers().etag;
		const b = ( await request.get( restUrl( 'posts_per_page=5&afs_term=events' ) ) ).headers().etag;
		expect( a ).not.toBe( b );
	} );
} );

test.describe( '1.x ajax action (my_action)', () => {
	test( 'returns a page of results, supports paging, and matches the REST route', async ( { request } ) => {
		expect( items( await ( await legacy( request, { filingType: '', filterBy: '', filterMonths: '', filterYears: '' } ) ).text() ) ).toBe( 10 );
		expect( items( await ( await legacy( request, { filingType: 'all', page: '2' } ) ).text() ) ).toBe( 10 );
		expect( items( await ( await legacy( request, { filingType: 'news' } ) ).text() ) ).toBe( items( await rest( request, 'afs_term=news&posts_per_page=10' ) ) );
		expect( items( await ( await legacy( request, { filingType: 'news,events' } ) ).text() ) ).toBe( items( await rest( request, 'terms=news,events&posts_per_page=10' ) ) );
		expect( items( await ( await legacy( request, { filterBy: 'Quarterly', filterYears: '2024' } ) ).text() ) ).toBe( items( await rest( request, 'afs_s=Quarterly&afs_year=2024&posts_per_page=10' ) ) );
	} );

	test( 'announces its deprecation', async ( { request } ) => {
		const res = await legacy( request, {} );
		expect( res.headers()[ 'x-afs-deprecated' ] ).toContain( 'deprecated' );
	} );

	test( 'cannot be used to run shortcodes through any field', async ( { request } ) => {
		for ( const f of [ 'filterBy', 'filingType', 'filterMonths', 'filterYears', 'page' ] ) {
			const html = await ( await legacy( request, { [ f ]: 'x"][caption]INJECTED-X[/caption][afs_feed a="' } ) ).text();
			expect( html, f ).not.toContain( 'INJECTED-X' );
		}
	} );

	test( 'never lists unpublished posts, escapes text, and survives hostile input', async ( { request } ) => {
		expect( items( await ( await legacy( request, { filterBy: 'SECRET' } ) ).text() ) ).toBe( 0 );
		expect( await ( await legacy( request, { filterBy: '<script>alert(1)</script>' } ) ).text() ).not.toContain( '<script>alert' );
		for ( const form of [ { page: '99999999999999999999' }, { page: '-5', filterMonths: 'abc', filterYears: '99999' }, { filterBy: 'A'.repeat( 100000 ) } ] ) {
			const res = await legacy( request, form );
			expect( res.status() ).toBeLessThan( 500 );
			expect( await res.text() ).not.toContain( 'critical error' );
		}
	} );

	test( 'array-typed parameters do not crash', async ( { request } ) => {
		const res = await request.post( '/wp-admin/admin-ajax.php', { data: 'action=my_action&filingType[]=x&filterBy[]=y&page[]=1', headers: { 'content-type': 'application/x-www-form-urlencoded' } } );
		expect( res.status() ).toBeLessThan( 500 );
		expect( await res.text() ).not.toContain( 'critical error' );
	} );

	test( 'the old admin-ajax taxonomy lookup is gone for visitors', async ( { request } ) => {
		const res = await request.post( '/wp-admin/admin-ajax.php', { form: { action: 'get_selected', option: 'post' } } );
		expect( await res.text() ).not.toContain( 'option' );
	} );
} );
