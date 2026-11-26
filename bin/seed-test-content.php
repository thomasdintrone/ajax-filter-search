<?php
/**
 * Dev only: seed a wp-env site with posts and test pages.
 *
 * Run:  npx wp-env run cli wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-test-content.php
 *
 * @package ajax-filter-search
 */

// WordPress's default post carries the install date, which would make date-based output differ per run.
$default = get_post( 1 );
if ( $default && 'Hello world!' === $default->post_title ) {
	wp_delete_post( 1, true );
}

$cats = array();
foreach ( array( 'News', 'Press Releases', 'Events' ) as $name ) {
	$term = wp_insert_term( $name, 'category' );
	$cats[ $name ] = is_wp_error( $term ) ? get_term_by( 'name', $name, 'category' )->term_id : $term['term_id'];
}

$names = array_keys( $cats );
$years = array( 2022, 2023, 2024, 2025, 2026 );

// A small deterministic cover image (a diagonal gradient from the post number), for the designs that show images.
require_once ABSPATH . 'wp-admin/includes/image.php';
function afs_seed_cover( $post_id, $i ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return;
	}
	$w  = 800;
	$h  = 500;
	$im = imagecreatetruecolor( $w, $h );
	$a  = array( ( $i * 53 ) % 200 + 30, ( $i * 97 ) % 200 + 30, ( $i * 29 ) % 200 + 30 );
	$b  = array( ( $i * 31 + 90 ) % 200 + 30, ( $i * 71 + 40 ) % 200 + 30, ( $i * 113 + 160 ) % 200 + 30 );
	for ( $y = 0; $y < $h; $y += 2 ) {
		for ( $x = 0; $x < $w; $x += 2 ) {
			$t = ( $x / $w + $y / $h ) / 2;
			imagefilledrectangle( $im, $x, $y, $x + 1, $y + 1, imagecolorallocate( $im, (int) ( $a[0] + ( $b[0] - $a[0] ) * $t ), (int) ( $a[1] + ( $b[1] - $a[1] ) * $t ), (int) ( $a[2] + ( $b[2] - $a[2] ) * $t ) ) );
		}
	}
	$up   = wp_upload_dir();
	$file = $up['path'] . '/afs-seed-cover-' . $i . '.jpg';
	imagejpeg( $im, $file, 80 );
	imagedestroy( $im );
	$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Cover ' . $i, 'post_status' => 'inherit' ), $file, $post_id );
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	set_post_thumbnail( $post_id, $att );
}

for ( $i = 1; $i <= 37; $i++ ) {
	$year = $years[ $i % 5 ];
	$date = sprintf( '%d-%02d-%02d 10:%02d:00', $year, ( $i % 12 ) + 1, ( $i % 27 ) + 1, $i % 60 );
	$post_id = wp_insert_post(
		array(
			'post_title'    => ( 0 === $i % 7 ? 'Quarterly results ' : 'Announcement ' ) . $i,
			'post_content'  => 'This is the body of post ' . $i . '. [gallery] Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.',
			'post_status'   => 'publish',
			'post_date'     => $date,
			'post_category' => array( $cats[ $names[ $i % 3 ] ] ),
		)
	);
	if ( 3 !== $i % 4 ) { // Every fourth post has no image, so the placeholder shows too.
		afs_seed_cover( $post_id, $i );
	}
}

// A draft and a private post that must never appear.
wp_insert_post( array( 'post_title' => 'SECRET DRAFT', 'post_status' => 'draft', 'post_content' => 'x' ) );
wp_insert_post( array( 'post_title' => 'SECRET PRIVATE', 'post_status' => 'private', 'post_content' => 'x' ) );

$page = wp_insert_post( array( 'post_title' => 'AFS Single', 'post_name' => 'afs-single', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search]' ) );
wp_insert_post( array( 'post_title' => 'AFS Double', 'post_name' => 'afs-double', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search posts_per_page="5" layout="grid"] [ajax_filter_search terms="news,events" posts_per_page="3" show_search="false"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Tools', 'post_name' => 'afs-tools', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search posts_per_page="5" show_sort="true" show_chips="true"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Classic', 'post_name' => 'afs-classic', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search design="classic" posts_per_page="5" show_sort="true" show_chips="true"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Editorial', 'post_name' => 'afs-editorial', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search design="editorial" posts_per_page="6" show_sort="true" show_chips="true"] [ajax_filter_search design="editorial" posts_per_page="3" show_tabs="false" show_chips="false"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Library', 'post_name' => 'afs-library', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search design="library" posts_per_page="6" show_sort="true" show_chips="true"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Legacy', 'post_name' => 'afs-legacy', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search filter_by="press-releases,events"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Block', 'post_name' => 'afs-block', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '<!-- wp:ajax-filter-search/feed {"perPage":4,"showViews":"hide"} /-->' ) );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
echo "seeded\n";
