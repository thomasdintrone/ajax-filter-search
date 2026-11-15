<?php
/**
 * Dev only: adds about 10,000 published posts (3 categories, 2022 to 2026) for performance checks.
 * Run after bin/seed-test-content.php:  wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-perf-content.php
 *
 * @package ajax-filter-search
 */

global $wpdb;
$tt   = array();
foreach ( array( 'news', 'press-releases', 'events' ) as $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );
	if ( $term ) {
		$tt[] = (int) $term->term_taxonomy_id;
	}
}
if ( count( $tt ) < 3 ) {
	echo "Run bin/seed-test-content.php first.\n";
	return;
}

$words = array( 'report', 'update', 'notice', 'summary', 'guide', 'results', 'quarterly', 'preview' );
$start = 100000;
$body  = str_repeat( 'Body text for the performance item. ', 12 );
for ( $chunk = 0; $chunk < 10000; $chunk += 500 ) {
	$posts = array();
	$rels  = array();
	for ( $i = $chunk; $i < $chunk + 500; $i++ ) {
		$id      = $start + $i;
		$date    = gmdate( 'Y-m-d H:i:s', strtotime( '2022-01-01 00:00:00' ) + ( $i * 17000 ) );
		$title   = ucfirst( $words[ $i % 8 ] ) . ' item ' . $i . ' ' . $words[ ( $i * 3 ) % 8 ];
		$posts[] = $wpdb->prepare( '(%d,1,%s,%s,%s,%s,"","publish","open","open","",%s,"","",%s,%s,"","","post",0,0)', $id, $date, $date, $body, $title, 'perf-' . $i, $date, $date ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
		$rels[]  = '(' . $id . ',' . $tt[ $i % 3 ] . ',0)';
	}
	$wpdb->query( "INSERT INTO {$wpdb->posts} (ID,post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,post_modified,post_modified_gmt,post_content_filtered,guid,post_type,menu_order,post_parent) VALUES " . implode( ',', $posts ) ); // phpcs:ignore
	$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id,term_taxonomy_id,term_order) VALUES " . implode( ',', $rels ) ); // phpcs:ignore
}
foreach ( $tt as $id ) {
	wp_update_term_count_now( array( $id ), 'category' );
}
wp_cache_flush();
echo "seeded 10000 performance posts\n";
