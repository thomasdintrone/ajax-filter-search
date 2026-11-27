import { test, expect } from '@playwright/test';
import { feed, settled } from './helpers.mjs';

// The 2.3.0 refinements of the Editorial Grid and Resource Library: one toolbar, a search placeholder, icon layout
// buttons, Reset only when it is needed, an empty state with one way out, aligned Library rows and a compact phone view.
// Classic and Modern are not part of this file: their behaviour is covered by the existing specs.

test.describe( 'toolbar', () => {
	test( 'the tabs and the layout switch share the first row, the filters take the second', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		const box = async ( selector ) => feed( page ).locator( selector ).first().boundingBox();
		const tabs = await box( '.afs-Tabs' );
		const sw = await box( '.afs-Switch' );
		const search = await box( '.afs-field-search' );
		expect( Math.abs( ( tabs.y + tabs.height / 2 ) - ( sw.y + sw.height / 2 ) ) ).toBeLessThan( 8 );
		expect( sw.x ).toBeGreaterThan( tabs.x + tabs.width - 1 );
		expect( search.y ).toBeGreaterThan( tabs.y + tabs.height );
	} );

	test( 'focus order follows the visual order: tabs, layout switch, search', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		await feed( page ).locator( '.afs-Tabs a' ).last().focus();
		await page.keyboard.press( 'Tab' );
		await expect( feed( page ).locator( '.afs-switch-btn' ).first() ).toBeFocused();
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );
		await expect( feed( page ).locator( 'input[name="afs_s"]' ) ).toBeFocused();
	} );

	test( 'the search field has a placeholder and keeps an accessible name', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		const search = feed( page ).getByRole( 'searchbox', { name: 'Search' } );
		await expect( search ).toHaveAttribute( 'placeholder', 'Search Posts' );
		const colour = await search.evaluate( ( el ) => getComputedStyle( el, '::placeholder' ).color );
		expect( colour ).not.toBe( '' );
	} );

	test( 'the layout switch is two icon buttons with accessible names and a pressed state', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		const grid = feed( page ).getByRole( 'button', { name: 'Grid View' } );
		const list = feed( page ).getByRole( 'button', { name: 'List View' } );
		await expect( grid ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( list ).toHaveAttribute( 'aria-pressed', 'false' );
		expect( await grid.locator( 'svg' ).count() ).toBe( 1 );
		const words = await grid.locator( '.afs-sr-only' ).boundingBox(); // The words are for screen readers only.
		expect( words.width ).toBeLessThanOrEqual( 1 );
		await list.click();
		await expect( list ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( grid ).toHaveAttribute( 'aria-pressed', 'false' );
		const size = await list.boundingBox();
		expect( size.width ).toBeGreaterThanOrEqual( 36 );
	} );
} );

test.describe( 'Reset', () => {
	test( 'with chips on there is no Reset button, only the chips and one Clear all', async ( { page } ) => {
		await page.goto( '/afs-editorial/?afs_term=news' );
		await settled( page );
		await expect( feed( page ).locator( '.afs-reset' ) ).toBeHidden();
		await expect( feed( page ).locator( '.afs-chip-clear' ) ).toBeVisible();
	} );

	test( 'with chips off, Reset appears only while a filter is active and clears it', async ( { page } ) => {
		// The second feed on the Editorial page has the chips off.
		await page.goto( '/afs-editorial/' );
		await settled( page, 2 );
		const plain = feed( page, 2 );
		const reset = plain.locator( '.afs-reset' );
		await expect( reset ).toBeHidden();
		await plain.locator( 'input[name="afs_s"]' ).fill( 'Announcement' );
		await settled( page, 2 );
		await expect( plain ).toHaveClass( /afs-is-filtered/ );
		await expect( reset ).toBeVisible();
		await reset.click();
		await settled( page, 2 );
		await expect( reset ).toBeHidden();
		await expect( plain.locator( 'input[name="afs_s"]' ) ).toHaveValue( '' );
	} );

	test( 'the filtered class is the same from the server and after an AJAX update', async ( { page, context } ) => {
		const root = ( p ) => feed( p );
		await page.goto( '/afs-editorial/?afs_term=news&afs_year=2024' );
		await settled( page );
		await expect( root( page ) ).toHaveClass( /afs-is-filtered/ );

		await root( page ).locator( '.afs-chip-clear' ).click();
		await settled( page );
		await expect( root( page ) ).not.toHaveClass( /afs-is-filtered/ );
		const idle = await context.newPage();
		await idle.goto( page.url() );
		await settled( idle );
		await expect( root( idle ) ).not.toHaveClass( /afs-is-filtered/ );

		await root( page ).locator( '.afs-Tabs a[data-afs-term="events"]' ).click();
		await settled( page );
		await expect( root( page ) ).toHaveClass( /afs-is-filtered/ );
		const filtered = await context.newPage();
		await filtered.goto( page.url() );
		await settled( filtered );
		await expect( root( filtered ) ).toHaveClass( /afs-is-filtered/ );

		// A sort is not a filter.
		await root( page ).locator( '.afs-chip-clear' ).click();
		await settled( page );
		await root( page ).locator( 'select[name="afs_sort"]' ).selectOption( 'oldest' );
		await settled( page );
		await expect( root( page ) ).not.toHaveClass( /afs-is-filtered/ );
	} );
} );

test.describe( 'empty state', () => {
	test( 'says what was searched and offers exactly one way to clear it', async ( { page } ) => {
		await page.goto( '/afs-editorial/?afs_s=zzzznomatch' );
		await settled( page );
		const root = feed( page );
		await expect( root.locator( '.afs-none__title' ) ).toHaveText( 'No results for “zzzznomatch”.' );
		await expect( root.locator( '.afs-none__hint' ) ).toBeVisible();
		await expect( root.locator( '[data-afs-clear="all"]' ) ).toHaveCount( 1 );
		await expect( root.locator( '.afs-reset' ) ).toBeHidden();
		await root.locator( '.afs-recover a' ).click();
		await settled( page );
		await expect( root.locator( '.afs-card' ).first() ).toBeVisible();
		await expect( root.locator( '.afs-none' ) ).toHaveCount( 0 );
	} );

	test( 'a typed search that finds nothing, through AJAX, shows the same message', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		await feed( page ).locator( 'input[name="afs_s"]' ).fill( 'qqqqzzzz' );
		await settled( page );
		await expect( feed( page ).locator( '.afs-none__title' ) ).toHaveText( 'No results for “qqqqzzzz”.' );
	} );
} );

test.describe( 'Resource Library', () => {
	test( 'every title starts at the same place, with or without an image', async ( { page } ) => {
		await page.goto( '/afs-library/' );
		await settled( page );
		const xs = await feed( page ).locator( '.afs-row h3' ).evaluateAll( ( els ) => els.map( ( el ) => Math.round( el.getBoundingClientRect().left ) ) );
		expect( xs.length ).toBeGreaterThan( 3 );
		expect( new Set( xs ).size ).toBe( 1 );
		// The seed leaves every fourth post without an image: some rows must be monogram tiles.
		expect( await feed( page ).locator( '.afs-row .afs-img--none' ).count() ).toBeGreaterThan( 0 );
		expect( await feed( page ).locator( '.afs-row img' ).count() ).toBeGreaterThan( 0 );
	} );
} );

test.describe( 'missing images and hover', () => {
	test( 'the Editorial Grid fallback is a tile with a monogram badge', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		const tile = feed( page ).locator( '.afs-img--none' ).first();
		await expect( tile ).toBeVisible();
		await expect( tile.locator( '.afs-img__mono' ) ).toHaveText( /^[A-Z]$/ );
		await expect( tile ).toHaveAttribute( 'aria-hidden', 'true' );
	} );

	test( 'the card shows a ring around the whole card for keyboard focus, and not for the mouse', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		const card = feed( page ).locator( '.afs-card' ).first();
		await card.locator( 'h3 a' ).focus();
		await page.keyboard.press( 'Shift+Tab' );
		await page.keyboard.press( 'Tab' );
		expect( await card.evaluate( ( el ) => getComputedStyle( el ).outlineStyle ) ).toBe( 'solid' );
		await card.hover();
		await page.locator( 'body' ).click( { position: { x: 5, y: 5 } } );
		expect( await card.evaluate( ( el ) => getComputedStyle( el ).outlineStyle ) ).not.toBe( 'solid' );
	} );
} );

test.describe( 'phone', () => {
	test.use( { viewport: { width: 390, height: 844 } } );

	test( 'the category pills wrap instead of clipping and the first result starts earlier', async ( { page } ) => {
		await page.goto( '/afs-editorial/' );
		await settled( page );
		const pills = await feed( page ).locator( '.afs-CommonTabs a' ).evaluateAll( ( els ) => els.map( ( el ) => { const r = el.getBoundingClientRect(); return { left: r.left, right: r.right }; } ) );
		expect( pills.length ).toBeGreaterThan( 2 );
		for ( const p of pills ) {
			expect( p.left ).toBeGreaterThanOrEqual( 0 );
			expect( p.right ).toBeLessThanOrEqual( 390 );
		}
		const nav = await feed( page ).locator( '.afs-Tabs' ).evaluate( ( el ) => el.scrollWidth <= el.clientWidth + 1 );
		expect( nav ).toBe( true );
		// No sideways scroll anywhere in the feed.
		expect( await page.evaluate( () => document.documentElement.scrollWidth <= innerWidth ) ).toBe( true );
		const first = await feed( page ).locator( '.afs-card' ).first().evaluate( ( el ) => el.getBoundingClientRect().top + window.scrollY );
		const rootTop = await feed( page ).evaluate( ( el ) => el.getBoundingClientRect().top + window.scrollY );
		console.log( `phone: first result ${ Math.round( first ) }px, controls before it ${ Math.round( first - rootTop ) }px` );
		expect( first - rootTop ).toBeLessThan( 430 ); // Was 457px before the 2.3.0 refinements.
	} );
} );

// On a phone the layout switch sits on the line of the result count. In both text directions it must not cover the count,
// the chips, an empty-result message or the first result.
test.describe( 'phone toolbar: the switch never overlaps', () => {
	const urls = {
		'a count and chips': '/afs-editorial/?afs_term=events&afs_s=Announcement',
		'chips that wrap': '/afs-editorial/?afs_term=press-releases&afs_s=Quarterly+results+membership+grows&afs_year=2026&afs_month=6',
		'an empty result': '/afs-editorial/?afs_s=zebra+submarine',
		'no filters': '/afs-editorial/',
	};
	for ( const dir of [ 'ltr', 'rtl' ] ) {
		for ( const [ name, url ] of Object.entries( urls ) ) {
			test( `${ dir }: ${ name }`, async ( { browser } ) => {
				const context = await browser.newContext( { viewport: { width: 390, height: 844 }, locale: 'en-US' } );
				const page = await context.newPage();
				await page.goto( url );
				await settled( page );
				if ( 'rtl' === dir ) {
					await page.evaluate( () => { document.documentElement.dir = 'rtl'; } );
					await page.waitForTimeout( 200 );
				}
				const rects = await page.evaluate( () => {
					const root = document.querySelector( '.afs[data-afs-instance="1"]' );
					const r = ( el ) => { const b = el.getBoundingClientRect(); return { x: b.left, y: b.top, w: b.width, h: b.height, label: el.className || el.tagName }; };
					const sw = root.querySelector( '.afs-Switch' );
					const others = [ ...root.querySelectorAll( '.afs-resultbar > *, .afs-chip, .afs-count, .afs-none, .afs-none__title, .afs-recover, .afs-item, .afs-card, .afs-Filters .afs-field, .afs-Tabs a' ) ];
					return { sw: r( sw ), others: others.map( r ), vw: document.documentElement.clientWidth, sx: document.documentElement.scrollWidth };
				} );
				const hit = ( a, b ) => a.x < b.x + b.w - 0.5 && b.x < a.x + a.w - 0.5 && a.y < b.y + b.h - 0.5 && b.y < a.y + a.h - 0.5;
				for ( const other of rects.others ) {
					expect( hit( rects.sw, other ), `the switch covers ${ other.label }` ).toBe( false );
				}
				expect( rects.sx, 'no sideways scrolling' ).toBeLessThanOrEqual( rects.vw );
				expect( rects.sw.x ).toBeGreaterThanOrEqual( 0 );
				expect( rects.sw.x + rects.sw.w ).toBeLessThanOrEqual( rects.vw );
				// The switch is on the end side: right in left-to-right text, left in right-to-left.
				const middle = rects.sw.x + rects.sw.w / 2;
				expect( 'rtl' === dir ? middle < rects.vw / 2 : middle > rects.vw / 2 ).toBe( true );
				await context.close();
			} );
		}
	}
} );
