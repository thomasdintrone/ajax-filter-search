<?php
/**
 * Dev only: seed a wp-env site with posts and test pages.
 *
 * Run:  npx wp-env run cli wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-test-content.php
 *
 * @package ajax-filter-search
 */

$cats = array();
foreach ( array( 'News', 'Press Releases', 'Events' ) as $name ) {
	$term = wp_insert_term( $name, 'category' );
	$cats[ $name ] = is_wp_error( $term ) ? get_term_by( 'name', $name, 'category' )->term_id : $term['term_id'];
}

$names = array_keys( $cats );
$years = array( 2022, 2023, 2024, 2025, 2026 );
for ( $i = 1; $i <= 37; $i++ ) {
	$year = $years[ $i % 5 ];
	$date = sprintf( '%d-%02d-%02d 10:%02d:00', $year, ( $i % 12 ) + 1, ( $i % 27 ) + 1, $i % 60 );
	wp_insert_post(
		array(
			'post_title'    => ( 0 === $i % 7 ? 'Quarterly results ' : 'Announcement ' ) . $i,
			'post_content'  => 'This is the body of post ' . $i . '. [gallery] Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.',
			'post_status'   => 'publish',
			'post_date'     => $date,
			'post_category' => array( $cats[ $names[ $i % 3 ] ] ),
		)
	);
}

// A draft and a private post that must never appear.
wp_insert_post( array( 'post_title' => 'SECRET DRAFT', 'post_status' => 'draft', 'post_content' => 'x' ) );
wp_insert_post( array( 'post_title' => 'SECRET PRIVATE', 'post_status' => 'private', 'post_content' => 'x' ) );

$page = wp_insert_post( array( 'post_title' => 'AFS Single', 'post_name' => 'afs-single', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search]' ) );
wp_insert_post( array( 'post_title' => 'AFS Double', 'post_name' => 'afs-double', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search posts_per_page="5" layout="grid"] [ajax_filter_search terms="news,events" posts_per_page="3" show_search="false"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Legacy', 'post_name' => 'afs-legacy', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[ajax_filter_search filter_by="press-releases,events"]' ) );
wp_insert_post( array( 'post_title' => 'AFS Block', 'post_name' => 'afs-block', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '<!-- wp:ajax-filter-search/feed {"perPage":4,"showViews":"hide"} /-->' ) );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
echo "seeded\n";
