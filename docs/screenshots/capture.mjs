// Takes the WordPress.org screenshots from the demo site (docs/screenshots/seed-demo.php) on http://localhost:8892.
//   node docs/screenshots/capture.mjs [outdir]      default outdir: .cache/screenshots
// Needs Google Chrome and `npm ci --ignore-scripts`. The admin pages use the throwaway test account of the local stack.
//
// The ten images (keep readme.txt's "== Screenshots ==" list in the same order):
//   1 Editorial Grid          2 Resource Library      3 Editorial Grid filtered, with chips and sort
//   4 Editorial Grid on a phone    5 A dark theme     6 Modern design      7 Two feeds on one page
//   8 Settings (design picker and Cards)   9 Block editor (Cards controls and term picker)   10 Classic design
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const BASE = process.env.AFS_DEMO_URL || 'http://localhost:8892';
const OUT = path.resolve( process.argv[ 2 ] || '.cache/screenshots' );
mkdirSync( OUT, { recursive: true } );
const browser = await chromium.launch( { channel: 'chrome' } );

/**
 * Wait until every feed on the page has finished loading and every image is in. `networkidle` is not enough: Playwright's
 * waitForLoadState returns at once when the page already reached that state earlier, so a request started by a click can
 * still be in flight (the first WordPress.org screenshot 2 showed "2025" with 2026 results, dimmed). The feed itself says
 * when it is done: no .afs-loading class and no aria-busy results.
 */
const settle = async ( page ) => {
	await page.waitForLoadState( 'load' );
	await page.waitForFunction( () => ! document.querySelector( '.afs-loading, .afs-results[aria-busy="true"]' ) );
	// Images are lazy: make them eager, then wait until each one has really loaded and decoded (`complete` is not enough
	// for an image the browser has not started yet).
	await page.evaluate( () => [ ...document.images ].forEach( ( img ) => { img.loading = 'eager'; } ) );
	await page.waitForFunction( () => [ ...document.images ].every( ( img ) => img.complete && img.naturalWidth > 0 ), null, { timeout: 30000 } );
	await page.evaluate( () => Promise.all( [ ...document.images ].map( ( img ) => img.decode().catch( () => 0 ) ) ) );
	await page.waitForTimeout( 300 );
};
const blur = async ( page ) => {
	// No leftover focus ring from the last click.
	await page.evaluate( () => document.activeElement && document.activeElement.blur && document.activeElement.blur() );
	await page.mouse.move( 0, 0 );
};
const shot = async ( page, name, height, width = 1200 ) => {
	await blur( page );
	return page.screenshot( { path: path.join( OUT, name ), fullPage: true, clip: { x: 0, y: 0, width, height } } );
};
const feed = ( page, n = 1 ) => page.locator( `.afs[data-afs-instance="${ n }"]` );

async function front( viewport, fn ) {
	const context = await browser.newContext( { viewport, locale: 'en-US', timezoneId: 'UTC', colorScheme: 'light' } );
	const page = await context.newPage();
	await fn( page );
	await context.close();
}

// 1. Editorial Grid.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/editorial/` ); await settle( page );
	await shot( page, 'screenshot-1.png', 1260 );
} );

// 2. Resource Library.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/library/` ); await settle( page );
	await shot( page, 'screenshot-2.png', 1020 );
} );

// 3. Editorial Grid filtered: a category, a year and a sort order, all kept in the address.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/editorial/` ); await settle( page );
	await feed( page ).locator( '.afs-Tabs a[data-afs-term="press-releases"]' ).click(); await settle( page );
	await feed( page ).locator( 'select[name="afs_year"]' ).selectOption( '2025' ); await settle( page );
	await feed( page ).locator( 'select[name="afs_sort"]' ).selectOption( 'oldest' ); await settle( page );
	// The screenshot must show what the filters say: Press Releases from 2025, oldest first.
	const dates = await feed( page ).locator( '.afs-card time' ).allTextContents();
	if ( ! dates.length || dates.some( ( d ) => ! d.includes( '2025' ) ) ) {
		throw new Error( 'Screenshot 3 would show results that do not match the filters: ' + JSON.stringify( dates ) );
	}
	await shot( page, 'screenshot-3.png', 1260 );
} );

// 4. Editorial Grid on a phone.
await front( { width: 390, height: 844 }, async ( page ) => {
	await page.goto( `${ BASE }/editorial/` ); await settle( page );
	await shot( page, 'screenshot-4.png', 1800, 390 );
} );

// 5. A dark theme (the page's group has dark colours; the design takes them from the theme).
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/dark/` ); await settle( page );
	await blur( page );
	const box = await page.locator( '.wp-block-group.has-background' ).first().boundingBox();
	await page.screenshot( { path: path.join( OUT, 'screenshot-5.png' ), fullPage: true, clip: { x: box.x - 24, y: box.y - 24, width: box.width + 48, height: Math.min( box.height, 1180 ) + 48 } } );
} );

// 6. The Modern design.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/press-room/` ); await settle( page );
	await shot( page, 'screenshot-6.png', 1150 );
} );

// 7. Two feeds on one page.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/newsroom/` ); await settle( page );
	await shot( page, 'screenshot-7.png', 2000 );
} );

// 10. The Classic design.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/classic/` ); await settle( page );
	await shot( page, 'screenshot-10.png', 1100 );
} );

// Admin screens (8 settings, 9 block editor).
const context = await browser.newContext( { viewport: { width: 1280, height: 1500 }, locale: 'en-US', timezoneId: 'UTC' } );
const admin = await context.newPage();
await admin.goto( `${ BASE }/wp-login.php` );
await admin.fill( '#user_login', 'admin' );
await admin.fill( '#user_pass', 'password' );
await Promise.all( [ admin.waitForURL( /wp-admin/ ), admin.click( '#wp-submit' ) ] );

await admin.goto( `${ BASE }/wp-admin/options-general.php?page=ajax-filter-search` ); await settle( admin );
await admin.addStyleTag( { content: '.afs-savebar{position:static!important;box-shadow:none!important}' } ); // A full-page capture would otherwise float it mid-page.
{
	// From the Appearance card (the design picker) to the end of the Cards card.
	const top = await admin.locator( '#afs-sec-appearance' ).boundingBox();
	const bottom = await admin.locator( '#afs-sec-cards' ).boundingBox();
	const left = ( await admin.locator( '.afs-admin__nav' ).boundingBox() ).x;
	await blur( admin );
	await admin.screenshot( { path: path.join( OUT, 'screenshot-8.png' ), fullPage: true, clip: { x: left - 16, y: top.y - 16, width: 1280 - left, height: bottom.y + bottom.height - top.y + 32 } } );
}

await admin.goto( `${ BASE }/wp-admin/edit.php?post_type=page&post_status=draft` );
const href = await admin.locator( 'a.row-title:has-text("Block demo")' ).first().getAttribute( 'href' );
await admin.goto( href );
// The editor canvas is an iframe on block themes.
const canvas = admin.frameLocator( 'iframe[name="editor-canvas"]' );
const block = canvas.locator( '[data-type="ajax-filter-search/feed"]' ).first();
await block.waitFor( { timeout: 60000 } );
await admin.waitForFunction( () => window.afsEditor );
await admin.evaluate( () => wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ) );
await canvas.locator( '.afs-card' ).first().waitFor( { timeout: 60000 } ); // The server-side preview loads after the editor.
await admin.waitForTimeout( 1500 );
await block.click( { position: { x: 8, y: 8 } } );
await admin.waitForTimeout( 800 );
if ( ! ( await admin.locator( '.interface-complementary-area' ).first().isVisible().catch( () => false ) ) ) {
	await admin.keyboard.press( 'Control+Shift+,' );
}
// The Content panel (with the term picker) and the Cards panel are open; the Display panel stays closed.
await admin.waitForTimeout( 800 );
await admin.evaluate( () => document.activeElement && document.activeElement.blur && document.activeElement.blur() );
await admin.mouse.move( 0, 0 );
await admin.screenshot( { path: path.join( OUT, 'screenshot-9.png' ) } );
await context.close();
await browser.close();
console.log( 'saved to', OUT );
