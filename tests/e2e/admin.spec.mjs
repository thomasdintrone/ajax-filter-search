import { test, expect } from '@playwright/test';
import { feed, settled } from './helpers.mjs';

// Admin-side journeys (2.3.0): choosing a design in the settings screen and using the block's term picker with the keyboard.
// They use the throwaway administrator account of the test stack (bin/e2e.sh installs it).

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );
}

const DESIGN = 'input[name="afs_settings[afs_appearance_design]"]';

async function chooseDesign( page, slug ) {
	await page.goto( '/wp-admin/options-general.php?page=ajax-filter-search' );
	await page.locator( `.afs-design-option--${ slug }` ).click();
	await Promise.all( [ page.waitForURL( /options-general\.php/ ), page.locator( '#submit' ).click() ] );
}

test.describe( 'onboarding', () => {
	let original = 'modern';

	test.beforeEach( async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/options-general.php?page=ajax-filter-search' );
		original = await page.locator( `${ DESIGN }:checked` ).getAttribute( 'value' );
	} );

	// The settings are shared with every other spec, so always put the original design back.
	test.afterEach( async ( { page } ) => {
		await chooseDesign( page, original );
	} );

	test( 'a site owner gets a styled Editorial Grid in five steps or fewer', async ( { page } ) => {
		const steps = [];

		steps.push( 'open Settings > Ajax Filter Search' );
		await page.goto( '/wp-admin/options-general.php?page=ajax-filter-search' );
		await expect( page.locator( '.afs-design-option--editorial' ) ).toBeVisible();

		steps.push( 'choose Editorial Grid' );
		await page.locator( '.afs-design-option--editorial' ).click();

		steps.push( 'save' );
		await Promise.all( [ page.waitForURL( /options-general\.php/ ), page.locator( '#submit' ).click() ] );
		await expect( page.locator( `${ DESIGN }:checked` ) ).toHaveAttribute( 'value', 'editorial' );

		// On a real site the owner adds the shortcode (or the block, or a pattern) to a page: one more step.
		// /afs-single/ already holds a bare [ajax_filter_search], which uses the site design.
		steps.push( 'view the page that has the shortcode' );
		await page.goto( '/afs-single/' );
		await settled( page );

		expect( steps.length ).toBeLessThanOrEqual( 5 );

		const root = feed( page );
		await expect( root ).toHaveClass( /afs-design-editorial/ );
		expect( await root.locator( '.afs-card' ).count() ).toBeGreaterThan( 0 );
		// Styled, not just marked up: the stylesheet is loaded and applies.
		expect( await page.locator( 'link[id="afs-editorial-css"]' ).count() ).toBe( 1 );
		const look = await root.locator( '.afs-items' ).evaluate( ( el ) => ( {
			display: getComputedStyle( el ).display,
			radius: getComputedStyle( el.querySelector( '.afs-card' ) ).borderTopLeftRadius,
		} ) );
		expect( look.display ).toBe( 'grid' );
		expect( look.radius ).not.toBe( '0px' );
	} );

	test( 'a feed that names its design is not changed by the site design', async ( { page } ) => {
		await chooseDesign( page, 'editorial' );
		await page.goto( '/afs-classic/' );
		await settled( page );
		await expect( feed( page ) ).not.toHaveClass( /afs-design-editorial/ );
		await expect( feed( page ).locator( '.afs-card' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'block editor', () => {
	test( 'the term picker can be used with the keyboard alone', async ( { page, request } ) => {
		// An existing page is used (a new, empty one opens WordPress's starter-pattern dialog, which takes focus).
		const pages = await ( await request.get( '/wp-json/wp/v2/pages?slug=afs-block&_fields=id' ) ).json();
		await login( page );
		await page.goto( `/wp-admin/post.php?post=${ pages[ 0 ].id }&action=edit` );
		await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/block-editor' ) && window.afsEditor && wp.data.select( 'core/block-editor' ).getBlocks().length > 0, null, { timeout: 60000 } );
		await page.evaluate( () => {
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			const block = wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ];
			// Nothing is saved: the page in the database is not touched.
			wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( block.clientId, { design: 'editorial', terms: '' } );
			wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
			wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
		} );

		const terms = () => page.evaluate( () => wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ].attributes.terms );
		const input = page.locator( '.components-form-token-field__input' );
		await input.waitFor( { timeout: 30000 } );

		// Ready means the block's preview has rendered (the editor has settled) and the picker has the site's terms:
		// focusing the empty field then lists them.
		await page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( '.afs-card' ).first().waitFor( { timeout: 30000 } );
		await expect( page.locator( '.components-modal__frame' ) ).toHaveCount( 0 );
		await input.focus();
		await expect( page.locator( '.components-form-token-field__suggestion' ).filter( { hasText: 'Press Releases' } ) ).toBeVisible();
		await page.keyboard.type( 'Pre' );
		await expect( page.locator( '.components-form-token-field__suggestion' ).first() ).toHaveText( 'Press Releases' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'Enter' );
		await expect.poll( terms ).toBe( 'press-releases' );
		await expect( page.locator( '.components-form-token-field__token-text' ).first() ).toContainText( 'Press Releases' );

		// A name that matches no term is not added.
		await page.keyboard.type( 'zzzz' );
		await page.keyboard.press( 'Enter' );
		expect( await terms() ).toBe( 'press-releases' );
		await page.keyboard.press( 'Control+a' );
		await page.keyboard.press( 'Backspace' );

		// Backspace in the empty field removes the last term.
		await page.keyboard.press( 'Backspace' );
		await expect.poll( terms ).toBe( '' );
	} );
} );
