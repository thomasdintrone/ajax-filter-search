// Renders the 2.3.0 review references to PNG next to the HTML: node design/prototypes/refinements/render.mjs
// (needs the repo devDependency playwright and Google Chrome). Static mockups; not production code.
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';

const dir = fileURLToPath( new URL( '.', import.meta.url ) ).replace( /\/$/, '' );
const browser = await chromium.launch( { channel: 'chrome' } );

async function shot( name, width, height, fn, fullPage = true ) {
	const context = await browser.newContext( { viewport: { width, height }, deviceScaleFactor: 1 } );
	const page = await context.newPage();
	await page.goto( `file://${ dir }/${ name }.html` );
	await page.evaluate( () => document.fonts && document.fonts.ready );
	if ( fn ) await fn( page );
	await page.waitForTimeout( 250 );
	return { page, context };
}
async function save( name, out, width, height, fn, fullPage = true ) {
	const { page, context } = await shot( name, width, height, fn );
	await page.screenshot( { path: `${ dir }/${ out }.png`, fullPage } );
	await context.close();
}

await save( 'editorial-idle', 'editorial-idle', 1300, 900 );
await save( 'editorial-active', 'editorial-active', 1300, 900 );
await save( 'library', 'library', 1300, 900 );
await save( 'mobile', 'mobile', 1460, 900 );
await save( 'whats-new', 'whats-new-desktop', 1360, 800 );
await save( 'whats-new', 'whats-new-mobile', 430, 900 );

// The interactive prototype in three states.
await save( 'cards-settings', 'cards-settings-editorial', 1280, 900 );
await save( 'cards-settings', 'cards-settings-options-off', 1280, 900, async ( page ) => {
	for ( const name of [ 'image', 'excerpt', 'date' ] ) await page.locator( `input[name="${ name }"][value="0"]` ).check( { force: true } );
} );
await save( 'cards-settings', 'cards-settings-modern-site', 1280, 700, async ( page ) => {
	await page.locator( 'input[name="sd"][value="modern"]' ).check();
} );

await browser.close();
console.log( 'rendered to', dir );
