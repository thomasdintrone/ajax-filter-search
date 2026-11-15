export const feed = ( page, n = 1 ) => page.locator( `.afs[data-afs-instance="${ n }"]` );
export const titles = ( page, n = 1 ) => feed( page, n ).locator( '.afs-results h3 a' ).allTextContents();

const decode = ( s ) => s.replace( /&#(\d+);/g, ( m, d ) => String.fromCharCode( +d ) ).replace( /&amp;/g, '&' ).trim();

/** Titles of feed n as the server renders them for a URL (what a visitor without JavaScript sees). */
export async function serverTitles( request, url, n = 1 ) {
	const html = await ( await request.get( url ) ).text();
	const block = html.split( /<div id="afs-wrapper/ ).slice( 1 )[ n - 1 ] || '';
	return [ ...block.matchAll( /<h3><a [^>]*>([^<]*)<\/a><\/h3>/g ) ].map( ( m ) => decode( m[ 1 ] ) );
}

/** Wait until a feed is no longer loading. */
export async function settled( page, n = 1 ) {
	await page.waitForLoadState( 'networkidle' );
	await page.locator( `.afs[data-afs-instance="${ n }"]:not(.afs-loading)` ).waitFor();
}
