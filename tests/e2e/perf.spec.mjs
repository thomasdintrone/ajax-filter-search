import { test, expect } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'node:fs';
import { feed, settled } from './helpers.mjs';

// Front-end budgets under a 4x CPU slowdown. Numbers are written to .cache/e2e-perf.json for docs/baselines.
// Interaction duration from the Event Timing API; the stack here is small, so this measures script cost, not database cost.
const BUDGET = { interaction_p75_ms: 200, cls: 0.1, js_gzip_kb: 12 };

test( 'interaction timing and layout shift stay inside the budget', async ( { page, context } ) => {
	const client = await context.newCDPSession( page );
	await client.send( 'Emulation.setCPUThrottlingRate', { rate: 4 } );
	await page.addInitScript( () => {
		window.__ev = [];
		window.__cls = 0;
		new PerformanceObserver( ( l ) => l.getEntries().forEach( ( e ) => { if ( e.interactionId > 0 ) window.__ev.push( e.duration ); } ) ).observe( { type: 'event', durationThreshold: 16, buffered: true } );
		new PerformanceObserver( ( l ) => l.getEntries().forEach( ( e ) => { if ( ! e.hadRecentInput ) window.__cls += e.value; } ) ).observe( { type: 'layout-shift', buffered: true } );
	} );
	await page.goto( '/afs-double/' );
	await settled( page );
	await page.evaluate( () => { window.__ev.length = 0; window.__cls = 0; } );

	for ( const term of [ 'news', 'events', 'press-releases' ] ) {
		await feed( page ).locator( `.afs-Tabs a[data-afs-term="${ term }"]` ).click();
		await settled( page );
		await feed( page ).locator( '[data-afs-layout="list"]' ).click();
		await feed( page ).locator( '[data-afs-layout="grid"]' ).click();
		await feed( page ).locator( 'input[name="afs_s"]' ).fill( '' );
		await feed( page ).locator( 'input[name="afs_s"]' ).pressSequentially( 'Quarterly', { delay: 60 } );
		await page.waitForTimeout( 600 );
		await settled( page );
		await feed( page ).locator( '.afs-reset' ).click();
		await settled( page );
	}
	const { ev, cls } = await page.evaluate( () => ( { ev: window.__ev, cls: window.__cls } ) );
	const sorted = [ ...ev ].sort( ( a, b ) => a - b );
	const p75 = sorted.length ? sorted[ Math.ceil( 0.75 * sorted.length ) - 1 ] : 0;

	const js = await page.evaluate( async () => {
		const src = [ ...document.scripts ].map( ( s ) => s.src ).filter( ( u ) => /ajax-filter-search\/public\/js/.test( u ) );
		let total = 0;
		for ( const u of src ) {
			const body = await ( await fetch( u ) ).arrayBuffer();
			const cs = new Response( new Blob( [ body ] ).stream().pipeThrough( new CompressionStream( 'gzip' ) ) );
			total += ( await cs.arrayBuffer() ).byteLength;
		}
		return total;
	} );

	mkdirSync( '../../.cache', { recursive: true } );
	writeFileSync( '../../.cache/e2e-perf.json', JSON.stringify( { cpu_throttle: 4, interactions: ev.length, interaction_p75_ms: p75, interaction_max_ms: Math.max( 0, ...ev ), cls: Number( cls.toFixed( 4 ) ), own_js_gzip_bytes: js }, null, 2 ) );

	expect( ev.length ).toBeGreaterThan( 5 );
	expect( p75 ).toBeLessThanOrEqual( BUDGET.interaction_p75_ms );
	expect( cls ).toBeLessThanOrEqual( BUDGET.cls );
	expect( js ).toBeLessThanOrEqual( BUDGET.js_gzip_kb * 1024 );
} );
