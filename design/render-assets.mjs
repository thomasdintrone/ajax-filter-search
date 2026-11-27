// SUPERSEDED (2.3.1 remediation): the banner and icon are now the owner-approved artwork in design/approved-artwork/, exported by
// design/export-approved-artwork.sh. Running this script would overwrite them with the older HTML/SVG drawings, so it stops here.
process.stderr.write( 'render-assets.mjs is superseded; use design/export-approved-artwork.sh\n' );
process.exit( 1 );

// Renders the WordPress.org icon and banner from their sources.
//   node design/render-assets.mjs          (needs Google Chrome and `npm ci --ignore-scripts`)
// Sources: design/icon.svg (the icon, exact vector geometry) and design/banner.html (the banner, which uses the icon).
// Output:  .wordpress-org/icon-128x128.png, icon-256x256.png, banner-772x250.png, banner-1544x500.png,
//          design/icon-master.png (1024 px, used by the banner) and design/previews/icon-32px-check.png.
import { chromium } from '@playwright/test';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const svg = readFileSync( path.join( root, 'design/icon.svg' ), 'utf8' );
const browser = await chromium.launch( { channel: 'chrome' } );

async function icon( size, file ) {
	const page = await browser.newPage( { viewport: { width: size, height: size }, deviceScaleFactor: 1 } );
	await page.setContent( `<html><body style="margin:0;background:transparent"><div style="width:${ size }px;height:${ size }px">${ svg.replace( /width="512" height="512"/, `width="${ size }" height="${ size }"` ) }</div></body></html>` );
	await page.screenshot( { path: file, omitBackground: true, clip: { x: 0, y: 0, width: size, height: size } } );
	await page.close();
}

await icon( 1024, path.join( root, 'design/icon-master.png' ) ); // The banner loads this one.
await icon( 256, path.join( root, '.wordpress-org/icon-256x256.png' ) );
await icon( 128, path.join( root, '.wordpress-org/icon-128x128.png' ) );
await icon( 32, path.join( root, 'design/previews/icon-32px-check.png' ) );

for ( const [ scale, name ] of [ [ 1, 'banner-1544x500.png' ], [ 0.5, 'banner-772x250.png' ] ] ) {
	const page = await browser.newPage( { viewport: { width: 1544, height: 500 }, deviceScaleFactor: scale } );
	await page.goto( pathToFileURL( path.join( root, 'design/banner.html' ) ).href );
	await page.waitForLoadState( 'load' );
	await page.screenshot( { path: path.join( root, '.wordpress-org', name ), clip: { x: 0, y: 0, width: 1544, height: 500 } } );
	await page.close();
}
await browser.close();
console.log( 'rendered' );
