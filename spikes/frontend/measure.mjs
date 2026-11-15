// Spike 1 and 2 harness. Needs the two spike stacks (spikes/frontend/setup-stack.sh) and puppeteer-core in .cache/spike-node.
//   node spikes/frontend/measure.mjs [stack ...]        stacks: 65 71 (default both). Output: .cache/spike-frontend-results.json
import { createRequire } from 'node:module';
import { gzipSync } from 'node:zlib';
import { writeFileSync, existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );
const require = createRequire( path.join( root, '.cache/spike-node/' ) );
const puppeteer = require( 'puppeteer-core' );
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const STACKS = { 65: 8865, 71: 8871 };
const VARIANTS = ( process.env.AFS_VARIANTS || 'baseline,interactivity,esm,router' ).split( ',' );
const PAGE = process.env.AFS_PAGE || '/afs-double/';
const THROTTLE = Number( process.env.AFS_THROTTLE || 4 );
const out = existsSync( path.join( root, '.cache/spike-frontend-results.json' ) ) ? JSON.parse( readFileSync( path.join( root, '.cache/spike-frontend-results.json' ), 'utf8' ) ) : {};
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
const p75 = ( a ) => { const s = [ ...a ].sort( ( x, y ) => x - y ); return s.length ? s[ Math.ceil( 0.75 * s.length ) - 1 ] : null; };

const feedTitles = ( page, i ) => page.evaluate( ( n ) => [ ...document.querySelectorAll( '.afs[data-afs-config]' )[ n ].querySelectorAll( '.afs-results h3 a' ) ].map( ( a ) => a.textContent.trim() ), i );
async function serverTitles( port, search, i ) {
	const html = await ( await fetch( `http://localhost:${ port }${ PAGE }${ search }${ search.includes( '?' ) ? '&' : '?' }afs_variant=baseline` ) ).text();
	const blocks = html.split( /<div id="afs-wrapper/ ).slice( 1 );
	return [ ...( blocks[ i ] || '' ).matchAll( /<h3><a [^>]*>([^<]*)<\/a><\/h3>/g ) ].map( ( m ) => m[ 1 ].replace( /&#(\d+);/g, ( x, n ) => String.fromCharCode( +n ) ).replace( /&amp;/g, '&' ).trim() );
}
async function settle( page ) {
	await page.waitForNetworkIdle( { idleTime: 250, timeout: 8000 } ).catch( () => {} );
	await sleep( 120 );
}
const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

async function newPage( browser, { throttle = 1, js = true } = {} ) {
	const page = await browser.newPage();
	await page.setViewport( { width: 1100, height: 900 } );
	await page.setJavaScriptEnabled( js );
	const client = await page.createCDPSession();
	if ( throttle > 1 ) await client.send( 'Emulation.setCPUThrottlingRate', { rate: throttle } );
	return { page, client };
}

async function weights( browser, port, variant, qs = '' ) {
	const { page, client } = await newPage( browser );
	await client.send( 'Network.enable' );
	await client.send( 'Network.setCacheDisabled', { cacheDisabled: true } );
	const reqs = new Map();
	client.on( 'Network.responseReceived', ( e ) => reqs.set( e.requestId, { url: e.response.url, type: e.type, status: e.response.status } ) );
	const done = [];
	client.on( 'Network.loadingFinished', ( e ) => done.push( e.requestId ) );
	await page.evaluateOnNewDocument( () => {
		window.__cls = 0;
		new PerformanceObserver( ( l ) => l.getEntries().forEach( ( e ) => { if ( ! e.hadRecentInput ) window.__cls += e.value; } ) ).observe( { type: 'layout-shift', buffered: true } );
	} );
	await page.goto( `http://localhost:${ port }${ PAGE }?afs_variant=${ variant }${ qs }`, { waitUntil: 'networkidle0' } );
	await sleep( 300 );
	const rows = [];
	for ( const id of done ) {
		const r = reqs.get( id );
		if ( ! r || ! /script|stylesheet|document/i.test( r.type ) ) continue;
		try {
			const b = await client.send( 'Network.getResponseBody', { requestId: id } );
			const buf = Buffer.from( b.body, b.base64Encoded ? 'base64' : 'utf8' );
			rows.push( { url: r.url.replace( /^http:\/\/localhost:\d+/, '' ).split( '?' )[ 0 ], type: r.type, raw: buf.length, gzip: gzipSync( buf, { level: 6 } ).length } );
		} catch ( e ) { /* body gone */ }
	}
	const cls = await page.evaluate( () => window.__cls );
	await page.close();
	const js = rows.filter( ( r ) => r.type === 'Script' && ! /jquery|navigation|wp-polyfill|emoji|admin-bar|wp-embed|block-library|wp-includes\/js\/(dist\/vendor|hoverintent)|view\.min/i.test( r.url ) || /interactivity|preact|afs/.test( r.url ) );
	return { rows, cls, js_total_gzip: js.reduce( ( s, r ) => s + r.gzip, 0 ), js_files: js.map( ( r ) => `${ r.url } ${ r.gzip }B gz` ) };
}

async function parity( browser, port, variant ) {
	const { page } = await newPage( browser );
	const res = {};
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e.message ).slice( 0, 160 ) ) );
	page.on( 'console', ( m ) => { if ( m.type() === 'error' ) errors.push( m.text().slice( 0, 160 ) ); } );
	await page.goto( `http://localhost:${ port }${ PAGE }?afs_variant=${ variant }`, { waitUntil: 'networkidle0' } );
	await sleep( 300 );
	const q = () => new URL( page.url() ).search;
	const check = async ( name, fn, expectSearch ) => {
		try {
			await fn();
			await settle( page );
			const got = await feedTitles( page, 0 );
			const exp = await serverTitles( port, expectSearch ? expectSearch() : q(), 0 );
			res[ name ] = { ok: same( got, exp ), empty: got.length === 0, got: got.length, expected: exp.length, url: q().replace( /afs_variant=\w+&?/, '' ) };
		} catch ( e ) { res[ name ] = { ok: false, error: String( e.message ).slice( 0, 120 ) }; }
	};
	res.initial = { ok: same( await feedTitles( page, 0 ), await serverTitles( port, '?', 0 ) ) };
	const f1 = '.afs[data-afs-instance="1"]';
	const f2 = '.afs[data-afs-instance="2"]';
	await check( 'tab click', () => page.click( `${ f1 } .afs-Tabs a[data-afs-term="press-releases"]` ) );
	await check( 'search typing', async () => { await page.click( `${ f1 } input[name="afs_s"]`, { clickCount: 3 } ); await page.keyboard.press( 'Backspace' ); await page.type( `${ f1 } input[name="afs_s"]`, 'Announcement', { delay: 40 } ); await sleep( 700 ); } );
	res[ 'search keeps focus' ] = { ok: await page.evaluate( () => document.activeElement && document.activeElement.name === 'afs_s' ) };
	await check( 'year select', () => page.select( `${ f1 } select[name="afs_year"]`, '2024' ) );
	await check( 'month select', () => page.select( `${ f1 } select[name="afs_month"]`, '3' ) );
	await check( 'reset', () => page.click( `${ f1 } .afs-reset` ) );
	await check( 'pagination page 2', () => page.click( `${ f1 } .afs-results a[data-afs-page="2"]` ) );
	await check( 'back button', async () => { await page.goBack(); await sleep( 400 ); } );
	const lay = async ( n ) => page.evaluate( ( x ) => x.map( ( s ) => document.querySelector( s ).classList.contains( 'afs-layout-grid' ) ).join( ',' ), n );
	try {
		await page.click( `${ f1 } [data-afs-layout="list"]` );
		await sleep( 150 );
		await page.click( `${ f1 } [data-afs-layout="grid"]` );
		await sleep( 200 );
		res[ 'layout toggle' ] = { ok: ( await lay( [ f1 ] ) ) === 'true' };
	} catch ( e ) { res[ 'layout toggle' ] = { ok: false, error: String( e.message ).slice( 0, 100 ) }; }
	const before1 = await feedTitles( page, 0 );
	await page.click( `${ f2 } .afs-Tabs a[data-afs-term="events"]` ).catch( () => {} );
	await settle( page );
	const after2 = await feedTitles( page, 1 );
	res[ 'second feed independent' ] = { ok: same( before1, await feedTitles( page, 0 ) ) && after2.length > 0, feed2_items: after2.length };
	try {
		const sum = await page.$( `${ f1 } .afs-summary-toggle` );
		if ( sum ) { await sum.click(); res[ 'summary toggle' ] = { ok: await page.evaluate( () => document.querySelector( '.afs-summary-toggle' ).getAttribute( 'aria-expanded' ) === 'true' ) }; }
	} catch ( e ) { res[ 'summary toggle' ] = { ok: false, error: String( e.message ).slice( 0, 100 ) }; }
	res[ 'busy cleared' ] = await page.evaluate( () => ( { aria_busy: [ ...document.querySelectorAll( '.afs-results' ) ].map( ( e ) => e.getAttribute( 'aria-busy' ) ), loading: document.querySelectorAll( '.afs-loading' ).length } ) );
	res.console_errors = errors.slice( 0, 5 );
	await page.close();
	return res;
}

async function inp( browser, port, variant ) {
	const { page } = await newPage( browser, { throttle: THROTTLE } );
	await page.evaluateOnNewDocument( () => {
		window.__ev = [];
		window.__cls2 = 0;
		new PerformanceObserver( ( l ) => l.getEntries().forEach( ( e ) => { if ( e.interactionId > 0 ) window.__ev.push( { n: e.name, d: e.duration, p: e.processingEnd - e.processingStart } ); } ) ).observe( { type: 'event', durationThreshold: 16, buffered: true } );
		new PerformanceObserver( ( l ) => l.getEntries().forEach( ( e ) => { if ( ! e.hadRecentInput ) window.__cls2 += e.value; } ) ).observe( { type: 'layout-shift', buffered: true } );
	} );
	await page.goto( `http://localhost:${ port }${ PAGE }?afs_variant=${ variant }`, { waitUntil: 'networkidle0' } );
	await sleep( 500 );
	await page.evaluate( () => { window.__cls2 = 0; window.__ev.length = 0; } );
	const f1 = '.afs[data-afs-instance="1"]';
	const f2 = '.afs[data-afs-instance="2"]';
	const labels = [];
	const step = async ( label, fn ) => { await page.evaluate( () => { window.__mark = window.__ev.length; } ); try { await fn(); } catch ( e ) { labels.push( { label: label + ' (skipped: ' + String( e.message ).slice( 0, 40 ) + ')', durations: [] } ); } await settle( page ); const n = await page.evaluate( () => window.__ev.slice( window.__mark ).map( ( e ) => e.d ) ); labels.push( { label, durations: n } ); };
	for ( let round = 0; round < 3; round++ ) {
		await step( 'tab', () => page.click( `${ f1 } .afs-Tabs a[data-afs-term="${ [ 'news', 'events', 'press-releases' ][ round ] }"]` ) );
		await step( 'select', () => page.select( `${ f1 } select[name="afs_year"]`, String( 2023 + round ) ) );
		await step( 'page', () => page.click( `${ f1 } .afs-results a[data-afs-page="2"]` ).catch( () => {} ) );
		await step( 'layout', () => page.click( `${ f1 } [data-afs-layout="${ round % 2 ? 'list' : 'grid' }"]` ) );
		await step( 'typing', async () => { await page.click( `${ f1 } input[name="afs_s"]`, { clickCount: 3 } ); await page.type( `${ f1 } input[name="afs_s"]`, 'Quarterly', { delay: 60 } ); await sleep( 600 ); } );
		await step( 'feed2 tab', () => page.click( `${ f2 } .afs-Tabs a[data-afs-term="${ round % 2 ? 'news' : 'events' }"]` ).catch( () => {} ) );
		await step( 'reset', () => page.click( `${ f1 } .afs-reset` ) );
	}
	const all = labels.flatMap( ( l ) => l.durations );
	const byType = {};
	labels.forEach( ( l ) => ( byType[ l.label ] = ( byType[ l.label ] || [] ).concat( l.durations ) ) );
	const cls = await page.evaluate( () => window.__cls2 );
	await page.close();
	return { throttle: THROTTLE, interactions: all.length, p75_ms: p75( all ), max_ms: Math.max( ...all ), by_type_p75: Object.fromEntries( Object.entries( byType ).map( ( [ k, v ] ) => [ k, p75( v ) ] ) ), cls_during_interactions: Number( cls.toFixed( 4 ) ) };
}

async function nojs( browser, port, variant ) {
	const { page } = await newPage( browser, { js: false } );
	await page.goto( `http://localhost:${ port }${ PAGE }?afs_variant=${ variant }&afs_term=news`, { waitUntil: 'load' } );
	const got = await feedTitles( page, 0 ).catch( () => [] );
	const exp = await serverTitles( port, `?afs_term=news&afs_variant=${ variant }`, 0 );
	const hasForm = await page.evaluate( () => !! document.querySelector( '.afs-form button[type="submit"]' ) );
	await page.click( '.afs[data-afs-instance="1"] .afs-results a[data-afs-page="2"]' ).catch( () => {} );
	await page.waitForNavigation( { timeout: 4000 } ).catch( () => {} );
	const p2 = await feedTitles( page, 0 ).catch( () => [] );
	await page.close();
	return { deep_link_matches_server: same( got, exp ) && got.length > 0, submit_button_present: hasForm, pagination_link_navigates: p2.length > 0 && ! same( p2, got ), url_after_click: page.url().replace( /^http:\/\/localhost:\d+/, '' ) };
}

async function proOrders( browser, port, variant ) {
	const res = {};
	for ( const when of [ 'first', 'last' ] ) {
		const { page } = await newPage( browser );
		await page.goto( `http://localhost:${ port }${ PAGE }?afs_variant=${ variant }&afs_pro=${ when }`, { waitUntil: 'networkidle0' } );
		await sleep( 300 );
		await page.click( '.afs[data-afs-instance="1"] .afs-Tabs a[data-afs-term="events"]' ).catch( () => {} );
		await settle( page );
		res[ `pro ${ when }` ] = await page.evaluate( () => ( {
			execution_order: ( window.__afsOrder || [] ).join( ' > ' ),
			new_key_present: document.documentElement.getAttribute( 'data-afs-pro-badge' ) === '1' || ( window.__proKeyFn === true ),
			override_took_effect: document.querySelector( '.afs[data-afs-instance="1"]' ).getAttribute( 'data-afs-pro-after' ) === '1',
			core_still_works: document.querySelectorAll( '.afs[data-afs-instance="1"] .afs-results article' ).length > 0,
		} ) );
		await page.close();
	}
	return res;
}

async function routerExtras( browser, port ) {
	const { page, client } = await newPage( browser );
	await client.send( 'Network.enable' );
	const sizes = [];
	const reqs = new Map();
	client.on( 'Network.responseReceived', ( e ) => reqs.set( e.requestId, e.response.url ) );
	client.on( 'Network.loadingFinished', async ( e ) => { const u = reqs.get( e.requestId ); if ( u && ( /afs_/.test( u ) || /wp-json/.test( u ) ) ) { try { const b = await client.send( 'Network.getResponseBody', { requestId: e.requestId } ); const buf = Buffer.from( b.body, b.base64Encoded ? 'base64' : 'utf8' ); sizes.push( { url: u.replace( /^http:\/\/localhost:\d+/, '' ).slice( 0, 90 ), raw: buf.length, gzip: gzipSync( buf, { level: 6 } ).length } ); } catch ( x ) { /* ignore */ } } } );
	await page.goto( `http://localhost:${ port }${ PAGE }?afs_variant=router`, { waitUntil: 'networkidle0' } );
	await sleep( 400 );
	await page.click( '.afs[data-afs-instance="2"] .afs-Tabs a[data-afs-term="events"]' ).catch( () => {} );
	await settle( page );
	const feed2Before = await feedTitles( page, 1 );
	await page.click( '.afs[data-afs-instance="1"] .afs-Tabs a[data-afs-term="news"]' );
	await settle( page );
	const feed2After = await feedTitles( page, 1 );
	await page.click( '.afs[data-afs-instance="1"] input[name="afs_s"]' );
	await page.keyboard.type( 'Ann', { delay: 80 } );
	await sleep( 900 );
	await settle( page );
	const focus = await page.evaluate( () => ( { still_focused: document.activeElement && document.activeElement.name === 'afs_s', value: ( document.querySelector( '.afs[data-afs-instance="1"] input[name="afs_s"]' ) || {} ).value } ) );
	await page.close();
	return { second_feed_state_survives_first_feed_navigation: same( feed2Before, feed2After ), search_input_after_typing: focus, requests: sizes.slice( 0, 6 ) };
}

const browser = await puppeteer.launch( { executablePath: CHROME, headless: 'new', args: [ '--no-sandbox' ] } );
const which = process.argv.slice( 2 ).length ? process.argv.slice( 2 ) : Object.keys( STACKS );
for ( const s of which ) {
	const port = STACKS[ s ];
	const key = `wp${ s }${ process.env.AFS_LABEL ? ' ' + process.env.AFS_LABEL : '' }`;
	out[ key ] = out[ key ] || {};
	Object.assign( out[ key ], { version: ( await ( await fetch( `http://localhost:${ port }/` ) ).text() ).match( /content="WordPress ([\d.]+)"/ )?.[ 1 ] } );
	for ( const v of VARIANTS ) {
		const r = {};
		r.weights = await weights( browser, port, v );
		r.parity = await parity( browser, port, v );
		r.inp = await inp( browser, port, v );
		r.nojs = await nojs( browser, port, v );
		if ( v === 'interactivity' || v === 'esm' ) r.pro = await proOrders( browser, port, v );
		if ( v === 'router' ) r.router = await routerExtras( browser, port );
		out[ key ][ v ] = r;
		if ( process.env.AFS_VERBOSE ) console.log( JSON.stringify( r.parity ) );
		console.log( key, v, 'js gz', r.weights.js_total_gzip, 'inp p75', r.inp.p75_ms, 'parity ok', Object.values( r.parity ).filter( ( x ) => x && x.ok === false ).length === 0 );
	}
}
writeFileSync( path.join( root, '.cache/spike-frontend-results.json' ), JSON.stringify( out, null, 1 ) );
await browser.close();
