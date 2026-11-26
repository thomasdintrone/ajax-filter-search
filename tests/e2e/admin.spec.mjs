import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
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

const SETTINGS = '/wp-admin/options-general.php?page=ajax-filter-search';
const row = ( page, name ) => page.locator( `[data-afs-needs="afs_settings[${ name }]"]` );

test.describe( 'Cards settings', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	// The settings are shared with every other spec: put the Cards defaults and the design back.
	test.afterEach( async ( { page } ) => {
		await page.goto( SETTINGS );
		await page.locator( '.afs-fold' ).evaluate( ( el ) => { el.open = true; } );
		for ( const name of [ 'afs_cards_show_image', 'afs_cards_show_excerpt', 'afs_cards_show_date' ] ) {
			await page.locator( `input[name="afs_settings[${ name }]"][value="1"]` ).check( { force: true } );
		}
		await page.locator( 'input[name="afs_settings[afs_cards_show_time]"][value="0"]' ).check( { force: true } );
		await page.locator( 'select[name="afs_settings[afs_cards_image_ratio]"]' ).selectOption( '16:10' );
		await page.locator( 'input[name="afs_settings[afs_cards_excerpt_length]"]' ).fill( '25' );
		await page.locator( `${ DESIGN }[value="modern"]` ).check( { force: true } );
		await Promise.all( [ page.waitForURL( /options-general\.php/ ), page.locator( '#submit' ).click() ] );
	} );

	test( 'the card is folded, with a note, while the site design is not one of the new ones, and opens from the navigation', async ( { page } ) => {
		await page.goto( SETTINGS );
		const fold = page.locator( '.afs-fold' );
		await expect( fold ).not.toHaveAttribute( 'open', '' );
		await expect( fold.locator( 'summary small' ) ).toContainText( 'Used by feeds that choose Editorial Grid or Resource Library' );
		await expect( page.locator( '.afs-callout' ) ).toContainText( 'how a feed overrides them' );
		await page.locator( '.afs-admin__nav a[href="#afs-sec-cards"]' ).click();
		await expect( fold ).toHaveAttribute( 'open', '' );
	} );

	test( 'the card is open when the site design is the Editorial Grid', async ( { page } ) => {
		await chooseDesign( page, 'editorial' );
		await page.goto( SETTINGS );
		await expect( page.locator( '.afs-fold' ) ).toHaveAttribute( 'open', '' );
		await expect( page.locator( '.afs-fold summary small' ) ).toHaveCount( 0 );
	} );

	test( 'dependent controls hide with their parent, and their saved values are kept', async ( { page } ) => {
		await page.goto( SETTINGS );
		await page.locator( '.afs-fold summary' ).click();
		const shape = page.locator( 'select[name="afs_settings[afs_cards_image_ratio]"]' );
		const length = page.locator( 'input[name="afs_settings[afs_cards_excerpt_length]"]' );
		await shape.selectOption( '3:2' );
		await length.fill( '33' );
		await expect( row( page, 'afs_cards_show_image' ) ).toBeVisible();

		await page.locator( 'input[name="afs_settings[afs_cards_show_image]"][value="0"]' ).check( { force: true } );
		await page.locator( 'input[name="afs_settings[afs_cards_show_excerpt]"][value="0"]' ).check( { force: true } );
		await page.locator( 'input[name="afs_settings[afs_cards_show_date]"][value="0"]' ).check( { force: true } );
		for ( const parent of [ 'afs_cards_show_image', 'afs_cards_show_excerpt', 'afs_cards_show_date' ] ) {
			await expect( row( page, parent ) ).toBeHidden();
		}
		await expect( page.locator( '.afs-kept' ) ).toBeVisible();

		await Promise.all( [ page.waitForURL( /options-general\.php/ ), page.locator( '#submit' ).click() ] );
		await page.locator( '.afs-fold summary' ).click();
		await expect( row( page, 'afs_cards_show_image' ) ).toBeHidden(); // Still hidden after a reload: the parent is still No.

		await page.locator( 'input[name="afs_settings[afs_cards_show_image]"][value="1"]' ).check( { force: true } );
		await page.locator( 'input[name="afs_settings[afs_cards_show_excerpt]"][value="1"]' ).check( { force: true } );
		await expect( shape ).toBeVisible();
		await expect( shape ).toHaveValue( '3:2' ); // Kept while hidden, and through a save.
		await expect( length ).toHaveValue( '33' );
		await expect( page.locator( '.afs-kept' ) ).toBeVisible(); // The date is still off, so the time is still hidden.
		await page.locator( 'input[name="afs_settings[afs_cards_show_date]"][value="1"]' ).check( { force: true } );
		await expect( page.locator( '.afs-kept' ) ).toBeHidden();
	} );
} );

test.describe( "What's new", () => {
	const REOPEN = `${ SETTINGS }&afs_whatsnew=1`;

	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'a fresh install does not see it, but can ask for it from the header', async ( { page } ) => {
		await page.goto( SETTINGS );
		await expect( page.locator( '#afs-news' ) ).toHaveCount( 0 );
		await page.getByRole( 'link', { name: "What's new" } ).click();
		await expect( page.locator( '#afs-news' ) ).toBeVisible();
		await expect( page.getByRole( 'region', { name: 'Meet the next generation of Ajax Filter Search.' } ) ).toBeVisible();
	} );

	test( 'has no accessibility violations and adds no outside request', async ( { page } ) => {
		// WordPress itself loads a few outside things on any admin screen (the avatar, for example). The card must add none:
		// the same screen without the card requests exactly the same addresses.
		const outsideOf = async ( context, url ) => {
			const seen = new Set();
			const tab = await context.newPage();
			tab.on( 'request', ( request ) => {
				const u = new URL( request.url() );
				if ( [ 'http:', 'https:' ].includes( u.protocol ) && u.hostname !== 'wordpress' ) seen.add( u.origin + u.pathname.replace( /[0-9a-f]{20,}/g, 'H' ) );
			} );
			await tab.goto( url );
			await tab.waitForLoadState( 'networkidle' );
			return [ ...seen ].sort();
		};
		const without = await outsideOf( page.context(), SETTINGS );
		const withCard = await outsideOf( page.context(), REOPEN );
		expect( withCard ).toEqual( without );

		await page.goto( REOPEN );
		await expect( page.locator( '#afs-news' ) ).toBeVisible();
		const { violations } = await new AxeBuilder( { page } ).include( '.afs-news' ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] ).analyze();
		expect( violations.map( ( v ) => `${ v.id }: ${ v.help }` ) ).toEqual( [] );
	} );

	test( 'fits a phone without sideways scrolling', async ( { page } ) => {
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( REOPEN );
		await expect( page.locator( '#afs-news' ) ).toBeVisible();
		expect( await page.evaluate( () => document.documentElement.scrollWidth <= innerWidth ) ).toBe( true );
		const box = await page.locator( '#afs-news' ).boundingBox();
		expect( box.x + box.width ).toBeLessThanOrEqual( 390 );
	} );

	test( 'the buttons are at least 44px tall and the dismiss button has a name', async ( { page } ) => {
		await page.goto( REOPEN );
		for ( const target of [ page.locator( '.afs-news__btn' ), page.locator( '.afs-news__later' ), page.locator( '.afs-news__x' ) ] ) {
			expect( ( await target.boundingBox() ).height ).toBeGreaterThanOrEqual( 43.5 );
		}
		await expect( page.getByRole( 'button', { name: 'Dismiss this announcement' } ) ).toBeVisible();
	} );

	test( 'Explore the new designs goes to the design picker', async ( { page } ) => {
		await page.goto( REOPEN );
		await page.getByRole( 'link', { name: 'Explore the new designs' } ).click();
		await expect( page ).toHaveURL( /#afs-sec-appearance$/ );
		await expect( page.locator( '.afs-design-option--editorial' ) ).toBeInViewport();
	} );

	test( 'dismissing removes it, tells a screen reader, keeps focus on the page heading and stays dismissed', async ( { page } ) => {
		await page.goto( REOPEN );
		await page.getByRole( 'button', { name: 'Dismiss this announcement' } ).click();
		await expect( page.locator( '#afs-news' ) ).toHaveCount( 0 );
		await expect( page.locator( '#afs-news-status' ) ).toHaveText( 'Announcement dismissed.' );
		await expect( page.locator( '#afs-admin-title' ) ).toBeFocused();
		await page.goto( SETTINGS );
		await expect( page.locator( '#afs-news' ) ).toHaveCount( 0 );
		await page.reload();
		await expect( page.locator( '#afs-news' ) ).toHaveCount( 0 );
		await expect( page.getByRole( 'link', { name: "What's new" } ) ).toBeVisible();
	} );

	test( 'Maybe later works without JavaScript: an ordinary form submit that comes back to the settings screen', async ( { browser } ) => {
		const context = await browser.newContext( { javaScriptEnabled: false } );
		const page = await context.newPage();
		await login( page );
		await page.goto( REOPEN );
		await expect( page.locator( '#afs-news' ) ).toBeVisible();
		await Promise.all( [ page.waitForURL( /options-general\.php\?page=ajax-filter-search$/ ), page.getByRole( 'button', { name: 'Maybe later' } ).click() ] );
		await expect( page.locator( '#afs-news' ) ).toHaveCount( 0 );
		await context.close();
	} );
} );
