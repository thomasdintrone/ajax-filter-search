// Downloads the free Unsplash photos listed in photos.json into docs/screenshots/photos/ (git-ignored), for the demo site.
//   node docs/screenshots/fetch-photos.mjs
// Each file is checked to be a JPEG of a sensible size before it is kept. Nothing is ever executed or opened as anything else.
import { readFile, writeFile, mkdir, access } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname( fileURLToPath( import.meta.url ) );
const out = path.join( dir, 'photos' );
const { photos } = JSON.parse( await readFile( path.join( dir, 'photos.json' ), 'utf8' ) );
await mkdir( out, { recursive: true } );

let failed = 0;
for ( const photo of photos ) {
	const file = path.join( out, `${ photo.id }.jpg` );
	try {
		await access( file );
		console.log( 'have  ', photo.id );
		continue;
	} catch ( error ) { /* not downloaded yet */ }
	// The free tier only: addresses on images.unsplash.com. 16:10, so the cards and the thumbnails crop the same way.
	const url = `https://images.unsplash.com/${ photo.cdn }?w=1600&h=1000&fit=crop&q=80&fm=jpg`;
	const response = await fetch( url );
	const bytes = Buffer.from( await response.arrayBuffer() );
	const isJpeg = bytes.length > 4 && bytes[ 0 ] === 0xff && bytes[ 1 ] === 0xd8 && bytes[ 2 ] === 0xff;
	if ( ! response.ok || ! isJpeg || bytes.length < 20000 || bytes.length > 3000000 ) {
		console.log( 'FAILED', photo.id, response.status, bytes.length );
		failed++;
		continue;
	}
	await writeFile( file, bytes );
	console.log( 'saved ', photo.id, Math.round( bytes.length / 1024 ) + ' KB', '-', photo.title );
}
if ( failed ) {
	process.exit( 1 );
}
