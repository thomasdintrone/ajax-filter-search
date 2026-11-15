import { test, expect } from '@playwright/test';
import { feed, titles, serverTitles, settled } from './helpers.mjs';

const REST = '**/wp-json/afs/v1/results*';
const chips = ( page ) => feed( page ).locator( '.afs-chips .afs-chip' );

test.describe( 'sort control, active-filter chips and empty-state recovery (opt-in on /afs-tools/)', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/afs-tools/' );
		await settled( page );
	} );

	test( 'choosing a sort reorders the results, matches the server, and the default stays out of the address bar', async ( { page, request } ) => {
		const select = feed( page ).locator( 'select[name="afs_sort"]' );
		await expect( select ).toHaveValue( 'newest' );
		await select.selectOption( 'oldest' );
		await settled( page );
		expect( page.url() ).toContain( 'afs_sort=oldest' );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await select.selectOption( 'title' );
		await settled( page );
		const sorted = await titles( page );
		expect( sorted ).toEqual( await serverTitles( request, page.url() ) );
		await select.selectOption( 'newest' );
		await settled( page );
		expect( page.url() ).not.toContain( 'afs_sort' );
	} );

	test( 'the sort survives paging and the back button', async ( { page } ) => {
		await feed( page ).locator( 'select[name="afs_sort"]' ).selectOption( 'oldest' );
		await settled( page );
		await feed( page ).locator( '.afs-pagination a[data-afs-page="2"]:not(.afs-next)' ).click();
		await settled( page );
		expect( page.url() ).toContain( 'afs_sort=oldest' );
		await page.goBack();
		await settled( page );
		await expect( feed( page ).locator( 'select[name="afs_sort"]' ) ).toHaveValue( 'oldest' );
	} );

	test( 'a chip appears for each active filter, removes only its filter, and Clear all keeps the sort', async ( { page, request } ) => {
		await expect( chips( page ) ).toHaveCount( 0 );
		await feed( page ).locator( 'select[name="afs_sort"]' ).selectOption( 'oldest' );
		await settled( page );
		await feed( page ).locator( '.afs-Tabs a[data-afs-term="news"]' ).click();
		await settled( page );
		await feed( page ).locator( 'select[name="afs_year"]' ).selectOption( '2024' );
		await settled( page );
		await expect( chips( page ) ).toHaveCount( 2 );
		await expect( chips( page ).first() ).toContainText( 'News' );

		await chips( page ).first().click();
		await settled( page );
		expect( page.url() ).not.toContain( 'afs_term' );
		expect( page.url() ).toContain( 'afs_year=2024' );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await expect( feed( page ).locator( 'select[name="afs_year"]' ) ).toHaveValue( '2024' );
		await expect( feed( page ).locator( '.afs-Tabs li.active a' ) ).toHaveAttribute( 'data-afs-term', '' );

		await feed( page ).locator( '.afs-chip-clear' ).click();
		await settled( page );
		await expect( chips( page ) ).toHaveCount( 0 );
		await expect( feed( page ).locator( 'select[name="afs_year"]' ) ).toHaveValue( '' );
		expect( page.url() ).toContain( 'afs_sort=oldest' );
		expect( page.url() ).not.toContain( 'afs_year' );
	} );

	test( 'an empty result explains itself and offers a way out', async ( { page } ) => {
		await feed( page ).locator( 'input[name="afs_s"]' ).fill( 'zzzznomatch' );
		await page.keyboard.press( 'Enter' );
		await settled( page );
		await expect( feed( page ).locator( '.afs-none' ) ).toBeVisible();
		await expect( chips( page ) ).toHaveCount( 1 );
		await feed( page ).locator( '.afs-recover a' ).click();
		await settled( page );
		expect( ( await titles( page ) ).length ).toBeGreaterThan( 0 );
		await expect( feed( page ).locator( 'input[name="afs_s"]' ) ).toHaveValue( '' );
	} );

	test( 'keyboard only: the sort select and the chips can be used without a mouse', async ( { page, request } ) => {
		const select = feed( page ).locator( 'select[name="afs_sort"]' );
		await select.focus();
		await page.keyboard.press( 'ArrowDown' ); // newest -> oldest
		await settled( page );
		expect( page.url() ).toContain( 'afs_sort=oldest' );
		await feed( page ).locator( 'input[name="afs_s"]' ).fill( 'Announcement' );
		await page.keyboard.press( 'Enter' );
		await settled( page );
		await chips( page ).first().focus();
		await page.keyboard.press( 'Enter' );
		await settled( page );
		expect( page.url() ).not.toContain( 'afs_s=' );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
	} );
} );

test.describe( 'without JavaScript', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'sort and chips are server-rendered and the chip links work', async ( { page, request } ) => {
		await page.goto( '/afs-tools/?afs_term=news&afs_year=2024&afs_sort=oldest' );
		await expect( feed( page ).locator( 'select[name="afs_sort"]' ) ).toHaveValue( 'oldest' );
		await expect( chips( page ) ).toHaveCount( 2 );
		expect( await titles( page ) ).toEqual( await serverTitles( request, '/afs-tools/?afs_term=news&afs_year=2024&afs_sort=oldest' ) );

		await chips( page ).first().click();
		await page.waitForLoadState( 'load' );
		expect( page.url() ).not.toContain( 'afs_term' );
		expect( page.url() ).toContain( 'afs_sort=oldest' );
		await expect( chips( page ) ).toHaveCount( 1 );
	} );

	test( 'the sort applies through the normal form submit', async ( { page } ) => {
		await page.goto( '/afs-tools/' );
		await feed( page ).locator( 'select[name="afs_sort"]' ).selectOption( 'title' );
		await feed( page ).locator( 'button[type="submit"]' ).click();
		await page.waitForURL( /afs_sort=title/ );
		await expect( feed( page ).locator( 'select[name="afs_sort"]' ) ).toHaveValue( 'title' );
	} );
} );

test.describe( 'error state with retry (a feed that does not own the address bar)', () => {
	test( 'shows a message with a Retry button that recovers', async ( { page } ) => {
		await page.goto( '/afs-double/' );
		await settled( page );
		await page.route( REST, ( route ) => route.abort() );
		await feed( page, 2 ).locator( '.afs-Tabs a[data-afs-term="events"]' ).click();
		const retry = feed( page, 2 ).locator( '.afs-error .afs-retry' );
		await expect( retry ).toBeVisible();
		await expect( feed( page, 2 ).locator( '.afs-error[role="alert"]' ) ).toBeVisible();
		await page.unroute( REST );
		await retry.click();
		await settled( page, 2 );
		await expect( feed( page, 2 ).locator( '.afs-error' ) ).toHaveCount( 0 );
		expect( ( await titles( page, 2 ) ).length ).toBeGreaterThan( 0 );
	} );
} );
