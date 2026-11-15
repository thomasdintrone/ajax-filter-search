import { test, expect } from '@playwright/test';
import { feed, titles, serverTitles, settled } from './helpers.mjs';

test.describe( 'filters, paging and history (JavaScript on)', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/afs-double/' );
		await settled( page );
	} );

	test( 'a category tab filters and the result matches the server render of the same URL', async ( { page, request } ) => {
		await feed( page ).locator( '.afs-Tabs a[data-afs-term="press-releases"]' ).click();
		await settled( page );
		expect( page.url() ).toContain( 'afs_term=press-releases' );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await expect( feed( page ).locator( '.afs-Tabs li.active a' ) ).toHaveAttribute( 'aria-current', 'true' );
	} );

	test( 'search is debounced, matches the server, and keeps focus', async ( { page, request } ) => {
		const box = feed( page ).locator( 'input[name="afs_s"]' );
		await box.click();
		await page.keyboard.type( 'Announcement', { delay: 40 } );
		await expect.poll( () => page.url() ).toContain( 'afs_s=Announcement' );
		await settled( page );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await expect( box ).toBeFocused();
	} );

	test( 'year and month selects, then reset', async ( { page, request } ) => {
		await feed( page ).locator( 'select[name="afs_year"]' ).selectOption( '2024' );
		await settled( page );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await feed( page ).locator( 'select[name="afs_month"]' ).selectOption( '3' );
		await settled( page );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await feed( page ).locator( '.afs-reset' ).click();
		await settled( page );
		expect( await titles( page ) ).toEqual( await serverTitles( request, '/afs-double/' ) );
		expect( new URL( page.url() ).search ).toBe( '' );
	} );

	test( 'pagination, then the back button restores the previous state', async ( { page, request } ) => {
		const first = await titles( page );
		await feed( page ).locator( '.afs-pagination a[data-afs-page="2"]:not(.afs-next)' ).click();
		await settled( page );
		expect( page.url() ).toContain( 'afs_page=2' );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await expect( feed( page ).locator( '.afs-pagination [aria-current="page"]' ) ).toHaveText( '2' );
		await page.goBack();
		await settled( page );
		expect( await titles( page ) ).toEqual( first );
	} );

	test( 'layout toggle and summary toggle', async ( { page } ) => {
		await feed( page ).locator( '[data-afs-layout="list"]' ).click();
		await expect( feed( page ) ).toHaveClass( /afs-layout-list/ );
		await feed( page ).locator( '[data-afs-layout="grid"]' ).click();
		await expect( feed( page ) ).toHaveClass( /afs-layout-grid/ );
		await expect( feed( page ).locator( '[data-afs-layout="grid"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
		const toggle = feed( page ).locator( '.afs-summary-toggle' ).first();
		await toggle.click();
		await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
	} );

	test( 'two feeds on one page are independent', async ( { page } ) => {
		const before = await titles( page, 1 );
		await feed( page, 2 ).locator( '.afs-Tabs a[data-afs-term="events"]' ).click();
		await settled( page, 2 );
		expect( await titles( page, 1 ) ).toEqual( before );
		expect( ( await titles( page, 2 ) ).length ).toBeGreaterThan( 0 );
	} );

	test( 'results region is a polite live region and is not left busy', async ( { page } ) => {
		await feed( page ).locator( '.afs-Tabs a[data-afs-term="news"]' ).click();
		await settled( page );
		await expect( feed( page ).locator( '.afs-results' ) ).toHaveAttribute( 'aria-live', 'polite' );
		await expect( feed( page ).locator( '.afs-results' ) ).toHaveAttribute( 'aria-busy', 'false' );
	} );
} );

test.describe( 'the Update button', () => {
	test( 'is hidden when JavaScript runs, and Enter in the search box still applies the search', async ( { page, request } ) => {
		await page.goto( '/afs-single/' );
		await settled( page );
		await expect( feed( page ).locator( '.afs-update' ) ).toBeHidden();
		await expect( feed( page ).locator( '.afs-reset' ) ).toBeVisible();
		await feed( page ).locator( 'input[name="afs_s"]' ).fill( 'Quarterly' );
		await page.keyboard.press( 'Enter' );
		await settled( page );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
	} );
} );

test.describe( 'keyboard only', () => {
	test( 'search, submit and page links work without a mouse', async ( { page, request } ) => {
		await page.goto( '/afs-single/' );
		await settled( page );
		await feed( page ).locator( 'input[name="afs_s"]' ).focus();
		await page.keyboard.type( 'Quarterly' );
		await page.keyboard.press( 'Enter' );
		await settled( page );
		expect( await titles( page ) ).toEqual( await serverTitles( request, page.url() ) );
		await feed( page ).locator( '.afs-reset' ).focus();
		await page.keyboard.press( 'Enter' );
		await settled( page );
		await feed( page ).locator( '.afs-pagination a[data-afs-page="2"]:not(.afs-next)' ).focus();
		await page.keyboard.press( 'Enter' );
		await settled( page );
		expect( page.url() ).toContain( 'afs_page=2' );
	} );
} );

test.describe( 'JavaScript off', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'a deep link renders the same results the server gives, and links page through', async ( { page, request } ) => {
		await page.goto( '/afs-single/?afs_term=news' );
		expect( await titles( page ) ).toEqual( await serverTitles( request, '/afs-single/?afs_term=news' ) );
		await expect( feed( page ).locator( 'button[type="submit"]' ) ).toBeVisible();
		await feed( page ).locator( '.afs-pagination a[data-afs-page="2"]:not(.afs-next)' ).click();
		await page.waitForURL( /afs_page=2/ );
		expect( ( await titles( page ) ).length ).toBeGreaterThan( 0 );
	} );

	test( 'the form submits as a normal GET', async ( { page } ) => {
		await page.goto( '/afs-single/' );
		await feed( page ).locator( 'input[name="afs_s"]' ).fill( 'Quarterly' );
		await feed( page ).locator( 'button[type="submit"]' ).click();
		await page.waitForURL( /afs_s=Quarterly/ );
		expect( ( await titles( page ) ).every( ( t ) => /Quarterly/i.test( t ) ) ).toBe( true );
	} );
} );

test.describe( 'small screens and the block', () => {
	test.use( { viewport: { width: 390, height: 800 } } );

	test( 'no horizontal overflow on a phone', async ( { page } ) => {
		for ( const path of [ '/afs-single/', '/afs-double/', '/afs-block/' ] ) {
			await page.goto( path );
			await settled( page );
			const overflow = await page.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
			expect( overflow, path ).toBeLessThanOrEqual( 0 );
		}
	} );

	test( 'the block page renders its configured number of items', async ( { page } ) => {
		await page.goto( '/afs-block/' );
		expect( ( await titles( page ) ).length ).toBe( 4 );
	} );
} );

test( 'drafts and private posts never show', async ( { page } ) => {
	await page.goto( '/afs-single/?afs_s=SECRET' );
	await expect( page.locator( '.afs-results' ).first() ).not.toContainText( 'SECRET' );
} );
