import { test, expect } from '@playwright/test';

// Nightly only (AFS_PERF_10K=1, after bin/seed-perf-content.php). Real HTTP timings of uncached requests against
// a site with about 10,000 posts. Budget from decision 0003: p95 <= 300 ms. The numbers go to .cache/e2e-perf-10k.json.
test.skip( ! process.env.AFS_PERF_10K, 'Nightly performance check (AFS_PERF_10K=1).' );

const p95 = ( a ) => { const s = [ ...a ].sort( ( x, y ) => x - y ); return s[ Math.ceil( 0.95 * s.length ) - 1 ]; };

test( 'REST results and the full page stay inside the latency budget at 10,000 posts', async ( { request } ) => {
	const states = [];
	for ( const t of [ '', 'news', 'press-releases', 'events' ] ) {
		for ( const y of [ 0, 2023, 2024 ] ) {
			for ( const m of [ 0, 6 ] ) states.push( `afs_term=${ t }&afs_year=${ y }&afs_month=${ m }` );
		}
	}
	states.push( 'afs_s=quarterly', 'afs_s=report%20item&afs_page=3', 'afs_page=40', 'afs_term=news&afs_s=update' );

	const time = async ( url ) => { const t = Date.now(); const r = await request.get( url ); await r.body(); expect( r.status() ).toBe( 200 ); return Date.now() - t; };
	const rest = [];
	const pages = [];
	for ( const q of states ) {
		rest.push( await time( `/wp-json/afs/v1/results?posts_per_page=12&${ q }` ) );
		pages.push( await time( `/afs-single/?${ q.replace( /afs_year=0&?|afs_month=0&?/g, '' ) }` ) );
	}
	const found = ( await ( await request.get( '/wp-json/afs/v1/results?posts_per_page=1' ) ).json() ).found;
	const out = { posts: found, states: states.length, rest_p95_ms: p95( rest ), rest_median_ms: [ ...rest ].sort( ( a, b ) => a - b )[ Math.floor( rest.length / 2 ) ], page_p95_ms: p95( pages ) };
	const { mkdirSync, writeFileSync } = await import( 'node:fs' );
	mkdirSync( '../../.cache', { recursive: true } );
	writeFileSync( '../../.cache/e2e-perf-10k.json', JSON.stringify( out, null, 2 ) );
	console.log( JSON.stringify( out ) );

	expect( found ).toBeGreaterThan( 9000 );
	expect( out.rest_p95_ms ).toBeLessThanOrEqual( 300 );
	expect( out.page_p95_ms ).toBeLessThanOrEqual( 600 );
} );
