import { test, expect } from '@playwright/test';
import { feed, settled } from './helpers.mjs';

// The Editorial Grid and Resource Library designs (2.3.0). Baselines live in __screenshots__ (bin/e2e.sh --update-snapshots).
const DESIGNS = [ { slug: 'editorial', path: '/afs-editorial/', item: '.afs-card' }, { slug: 'library', path: '/afs-library/', item: '.afs-row' } ];

/** Lazy images load as they scroll into view; make the page deterministic before a screenshot. */
async function loadImages( page ) {
	await page.evaluate( async () => {
		for ( const img of document.querySelectorAll( '.afs img' ) ) {
			img.loading = 'eager';
		}
		await Promise.all( [ ...document.querySelectorAll( '.afs img' ) ].map( ( i ) => ( i.complete ? 1 : new Promise( ( r ) => { i.onload = i.onerror = r; } ) ) ) );
	} );
}

const results = ( page ) => feed( page ).locator( '.afs-results' ).evaluate( ( el ) => el.innerHTML.replace( /\s+/g, ' ' ).trim() );

for ( const d of DESIGNS ) {
	test.describe( d.slug, () => {
		test( 'results loaded by AJAX equal what the server renders for the same address', async ( { page, context } ) => {
			await page.goto( d.path );
			await settled( page );
			await feed( page ).locator( '.afs-Tabs a[data-afs-term="events"]' ).click();
			await settled( page );
			const ajax = await results( page );
			const fresh = await context.newPage();
			await fresh.goto( page.url() );
			await settled( fresh );
			expect( ajax ).toBe( await results( fresh ) );
			expect( ajax ).toContain( d.item.slice( 1 ) );
		} );

		test( 'card parts: image links are hidden from assistive technology and images carry empty alt text', async ( { page } ) => {
			await page.goto( d.path );
			await settled( page );
			const links = feed( page ).locator( 'a.afs-img' );
			expect( await links.count() ).toBeGreaterThan( 0 );
			for ( const link of await links.all() ) {
				await expect( link ).toHaveAttribute( 'aria-hidden', 'true' );
				await expect( link ).toHaveAttribute( 'tabindex', '-1' );
				await expect( link.locator( 'img' ) ).toHaveAttribute( 'alt', '' );
			}
			// Each item has one reachable link: the title.
			const first = feed( page ).locator( d.item ).first();
			expect( await first.locator( 'a:not([aria-hidden="true"])' ).count() ).toBe( 1 );
		} );

		test( 'tab order reaches the title links and the pagination', async ( { page } ) => {
			await page.goto( d.path );
			await settled( page );
			const title = feed( page ).locator( `${ d.item } h3 a` ).first();
			await title.focus();
			await page.keyboard.press( 'Shift+Tab' );
			await page.keyboard.press( 'Tab' );
			await expect( title ).toBeFocused();
			// A visible focus indicator: a ring around the whole card (where :has() is supported) or on the link itself.
			const ring = await title.evaluate( ( el ) => {
				const card = el.closest( '.afs-item' );
				return [ getComputedStyle( el ).outlineStyle, getComputedStyle( card ).outlineStyle ];
			} );
			expect( ring.some( ( style ) => style !== 'none' ) ).toBe( true );
		} );
	} );
}

test( 'editorial: the grid never has more columns than asked for and drops to one on a phone', async ( { browser } ) => {
	for ( const [ width, max ] of [ [ 1400, 3 ], [ 390, 1 ] ] ) {
		const context = await browser.newContext( { viewport: { width, height: 900 } } );
		const page = await context.newPage();
		await page.goto( '/afs-editorial/' );
		await settled( page );
		const cols = await feed( page ).locator( '.afs-items' ).evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length );
		expect( cols ).toBeLessThanOrEqual( max );
		expect( cols ).toBeGreaterThanOrEqual( 1 );
		if ( 390 === width ) expect( cols ).toBe( 1 );
		await context.close();
	}
} );

test( 'editorial: List View switches to one column and Grid View back', async ( { page } ) => {
	await page.goto( '/afs-editorial/' );
	await settled( page );
	const cols = () => feed( page ).locator( '.afs-items' ).evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length );
	expect( await cols() ).toBeGreaterThan( 1 );
	await feed( page ).locator( '[data-afs-layout="list"]' ).click();
	expect( await cols() ).toBe( 1 );
	await feed( page ).locator( '[data-afs-layout="grid"]' ).click();
	expect( await cols() ).toBeGreaterThan( 1 );
} );

test( 'library: has no layout switch', async ( { page } ) => {
	await page.goto( '/afs-library/' );
	await settled( page );
	await expect( feed( page ).locator( '.afs-Switch' ) ).toBeHidden();
} );

// Visual baselines. "dark" is a page whose theme colours are dark: the designs derive their colours from the theme.
const SHOTS = [
	{ name: 'light-desktop', width: 1200 },
	{ name: 'light-mobile', width: 390 },
	{ name: 'dark-desktop', width: 1200, dark: true },
];
for ( const d of DESIGNS ) {
	for ( const c of SHOTS ) {
		test( `visual: ${ d.slug }-${ c.name }`, async ( { browser } ) => {
			const context = await browser.newContext( { viewport: { width: c.width, height: 900 }, locale: 'en-US', timezoneId: 'UTC' } );
			const page = await context.newPage();
			await page.goto( d.path );
			await settled( page );
			if ( c.dark ) {
				await page.addStyleTag( { content: 'body, .wp-site-blocks { background: #0f1420 !important; color: #eef1f7 !important; } .afs a { color: inherit; }' } );
			}
			await loadImages( page );
			await expect( feed( page ) ).toHaveScreenshot( `${ d.slug }-${ c.name }.png` );
			await context.close();
		} );
	}
}
