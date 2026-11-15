import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { feed, settled } from './helpers.mjs';

// Scans only the plugin's own region (.afs); the theme's markup is not ours to fix.
const TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];
const PAGES = [ '/afs-single/', '/afs-double/', '/afs-block/', '/afs-single/?afs_term=news&afs_year=2024', '/afs-legacy/' ];

async function scan( page ) {
	const { violations } = await new AxeBuilder( { page } ).include( '.afs' ).withTags( TAGS ).analyze();
	return violations.map( ( v ) => `${ v.id }: ${ v.help } (${ v.nodes.length } nodes) ${ v.nodes[ 0 ].target.join( ' ' ) }` );
}

for ( const scheme of [ 'light', 'dark' ] ) {
	for ( const width of [ 390, 1200 ] ) {
		test.describe( `${ scheme }, ${ width }px`, () => {
			test.use( { colorScheme: scheme, viewport: { width, height: 900 } } );

			for ( const path of PAGES ) {
				test( `axe: ${ path }`, async ( { page } ) => {
					await page.goto( path );
					await settled( page );
					expect( await scan( page ) ).toEqual( [] );
				} );
			}

			test( 'axe: after interactions (filtered, paged, expanded summary)', async ( { page } ) => {
				await page.goto( '/afs-single/' );
				await settled( page );
				await feed( page ).locator( '.afs-Tabs a[data-afs-term="news"]' ).click();
				await settled( page );
				await feed( page ).locator( '.afs-pagination a[data-afs-page="2"]:not(.afs-next)' ).click();
				await settled( page );
				await feed( page ).locator( '.afs-summary-toggle' ).first().click();
				expect( await scan( page ) ).toEqual( [] );
			} );
		} );
	}
}
