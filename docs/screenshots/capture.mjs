// Takes the WordPress.org screenshots from the demo site (docs/screenshots/seed-demo.php) on http://localhost:8892.
//   node docs/screenshots/capture.mjs [outdir]      default outdir: .cache/screenshots
// Needs Google Chrome and `npm ci --ignore-scripts`. The admin pages use the throwaway test account of the local stack.
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const BASE = process.env.AFS_DEMO_URL || 'http://localhost:8892';
const OUT = path.resolve( process.argv[ 2 ] || '.cache/screenshots' );
mkdirSync( OUT, { recursive: true } );
const browser = await chromium.launch( { channel: 'chrome' } );
const settle = async ( page ) => { await page.waitForLoadState( 'networkidle' ); await page.waitForTimeout( 400 ); };
const shot = async ( page, name, height, width = 1200 ) => {
	// No leftover focus ring from the last click.
	await page.evaluate( () => document.activeElement && document.activeElement.blur && document.activeElement.blur() );
	await page.mouse.move( 0, 0 );
	return page.screenshot( { path: path.join( OUT, name ), fullPage: true, clip: { x: 0, y: 0, width, height } } );
};
const feed = ( page, n = 1 ) => page.locator( `.afs[data-afs-instance="${ n }"]` );

async function front( viewport, fn ) {
	const context = await browser.newContext( { viewport, locale: 'en-US', timezoneId: 'UTC', colorScheme: 'light' } );
	const page = await context.newPage();
	await fn( page );
	await context.close();
}

// 1. The default feed.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/press-room/` ); await settle( page );
	await shot( page, 'screenshot-1.png', 1150 );
} );

// 2. Filtered results, state kept in the address bar.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/press-room/` ); await settle( page );
	await feed( page ).locator( '.afs-Tabs a[data-afs-term="press-releases"]' ).click(); await settle( page );
	await feed( page ).locator( 'select[name="afs_year"]' ).selectOption( '2025' ); await settle( page );
	await shot( page, 'screenshot-2.png', 1000 );
} );

// 3. Grid layout.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/resources/` ); await settle( page );
	await shot( page, 'screenshot-3.png', 1150 );
} );

// 4. Two feeds on one page.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/newsroom/` ); await settle( page );
	await shot( page, 'screenshot-4.png', 2000 );
} );

// 7. A phone.
await front( { width: 390, height: 844 }, async ( page ) => {
	await page.goto( `${ BASE }/press-room/` ); await settle( page );
	await shot( page, 'screenshot-7.png', 1450, 390 );
} );

// 8. Sort control and active filter chips.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/archive/` ); await settle( page );
	await feed( page ).locator( '.afs-Tabs a[data-afs-term="news"]' ).click(); await settle( page );
	await feed( page ).locator( 'select[name="afs_year"]' ).selectOption( '2025' ); await settle( page );
	await feed( page ).locator( 'select[name="afs_sort"]' ).selectOption( 'oldest' ); await settle( page );
	await shot( page, 'screenshot-8.png', 1000 );
} );

// 9. An empty result with a way out.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/archive/` ); await settle( page );
	await feed( page ).locator( 'input[name="afs_s"]' ).fill( 'moonbase' );
	await page.keyboard.press( 'Enter' ); await settle( page );
	await shot( page, 'screenshot-9.png', 860 );
} );

// 10. The Classic design, for sites that prefer the 2.x look.
await front( { width: 1200, height: 900 }, async ( page ) => {
	await page.goto( `${ BASE }/classic/` ); await settle( page );
	await shot( page, 'screenshot-10.png', 1100 );
} );

// Admin screens (5 settings, 6 block editor).
const context = await browser.newContext( { viewport: { width: 1280, height: 1000 }, locale: 'en-US', timezoneId: 'UTC' } );
const admin = await context.newPage();
await admin.goto( `${ BASE }/wp-login.php` );
await admin.fill( '#user_login', 'admin' );
await admin.fill( '#user_pass', 'password' );
await Promise.all( [ admin.waitForURL( /wp-admin/ ), admin.click( '#wp-submit' ) ] );

await admin.goto( `${ BASE }/wp-admin/options-general.php?page=ajax-filter-search` ); await settle( admin );
await admin.addStyleTag( { content: '.afs-savebar{position:static!important;box-shadow:none!important}' } ); // A full-page capture would otherwise float it mid-page.
await admin.screenshot( { path: path.join( OUT, 'screenshot-5.png' ), fullPage: true, clip: { x: 0, y: 0, width: 1280, height: 1700 } } );

await admin.goto( `${ BASE }/wp-admin/edit.php?post_type=page&post_status=draft` );
const href = await admin.locator( 'a.row-title:has-text("Block demo")' ).first().getAttribute( 'href' );
await admin.goto( href );
// The editor canvas is an iframe on block themes.
const canvas = admin.frameLocator( 'iframe[name="editor-canvas"]' );
const block = canvas.locator( '[data-type="ajax-filter-search/feed"]' ).first();
await block.waitFor( { timeout: 60000 } );
await admin.waitForTimeout( 3500 ); // The server-side preview loads after the editor.
await block.click( { position: { x: 8, y: 8 } } );
await admin.waitForTimeout( 800 );
if ( ! ( await admin.locator( '.interface-complementary-area' ).first().isVisible().catch( () => false ) ) ) {
	await admin.keyboard.press( 'Control+Shift+,' );
}
// Open the Display panel (it holds the layout, sort and chip controls), then scroll them into view.
const display = admin.locator( '.interface-complementary-area button.components-panel__body-toggle:has-text("Display")' ).first();
if ( ( await display.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
	await display.click();
}
await admin.waitForTimeout( 500 );
await admin.evaluate( () => {
	const panel = document.querySelector( '.interface-complementary-area' );
	const target = Array.from( document.querySelectorAll( '.interface-complementary-area label, .interface-complementary-area legend' ) ).find( ( el ) => /Sort control/i.test( el.textContent ) );
	if ( target ) {
		target.scrollIntoView( { block: 'center' } );
	} else if ( panel ) {
		panel.scrollTop = 600;
	}
} );
await admin.evaluate( () => document.activeElement && document.activeElement.blur && document.activeElement.blur() );
await admin.waitForTimeout( 800 );
await admin.screenshot( { path: path.join( OUT, 'screenshot-6.png' ) } );
await context.close();
await browser.close();
console.log( 'saved to', OUT );
