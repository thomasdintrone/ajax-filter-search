import { test, expect } from '@playwright/test';
import { feed, settled } from './helpers.mjs';

// Baselines live in __screenshots__ and are only meaningful inside the Playwright image (bin/e2e.sh).
// Review the images in any diff before accepting them: bin/e2e.sh --update-snapshots
const cases = [
	{ name: 'list-light-desktop', scheme: 'light', width: 1200 },
	{ name: 'list-dark-desktop', scheme: 'dark', width: 1200 },
	{ name: 'list-light-mobile', scheme: 'light', width: 390 },
	{ name: 'list-rtl-desktop', scheme: 'light', width: 1200, rtl: true },
];

for ( const c of cases ) {
	test( `visual: ${ c.name }`, async ( { browser } ) => {
		const context = await browser.newContext( { colorScheme: c.scheme, viewport: { width: c.width, height: 900 }, locale: 'en-US', timezoneId: 'UTC' } );
		const page = await context.newPage();
		await page.goto( '/afs-single/' );
		await settled( page );
		if ( c.rtl ) await page.evaluate( () => { document.documentElement.dir = 'rtl'; } );
		await expect( feed( page ) ).toHaveScreenshot( `${ c.name }.png` );
		await context.close();
	} );
}

test( 'visual: grid layout, second page', async ( { page } ) => {
	await page.goto( '/afs-double/?afs_page=2' );
	await settled( page );
	await expect( feed( page ) ).toHaveScreenshot( 'grid-page2.png' );
} );

test( 'visual: sort control, chips and Clear all', async ( { page } ) => {
	await page.goto( '/afs-tools/?afs_term=news&afs_year=2024&afs_sort=oldest' );
	await settled( page );
	await expect( feed( page ) ).toHaveScreenshot( 'tools-chips.png' );
} );

test( 'visual: empty state with recovery', async ( { page } ) => {
	await page.goto( '/afs-tools/?afs_s=zzzznomatch' );
	await settled( page );
	await expect( feed( page ) ).toHaveScreenshot( 'tools-empty.png' );
} );

// The Classic design (the 2.0 look) must keep looking the same: it has its own baselines.
for ( const c of [ { name: 'classic-light-desktop', width: 1200 }, { name: 'classic-light-mobile', width: 390 } ] ) {
	test( `visual: ${ c.name }`, async ( { browser } ) => {
		const context = await browser.newContext( { viewport: { width: c.width, height: 900 }, locale: 'en-US', timezoneId: 'UTC' } );
		const page = await context.newPage();
		await page.goto( '/afs-classic/?afs_term=news&afs_year=2024' );
		await settled( page );
		await expect( feed( page ) ).toHaveScreenshot( `${ c.name }.png` );
		await context.close();
	} );
}

