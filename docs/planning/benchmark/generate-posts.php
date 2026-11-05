<?php
// temp benchmark data generator: php args: target total posts
$target = (int) $args[0];
$cats = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids' ) );
if ( count( $cats ) < 40 ) { for ( $i = 1; $i <= 40; $i++ ) { $t = wp_insert_term( "Bench Category $i", 'category' ); if ( ! is_wp_error( $t ) ) { $cats[] = $t['term_id']; } } }
$have = (int) wp_count_posts( 'post' )->publish;
$words = explode( ' ', 'filter search archive library press release report update guide review story event notice analysis summary policy research interview feature digest bulletin' );
wp_defer_term_counting( true ); wp_suspend_cache_invalidation( true );
$t0 = microtime( true ); mt_srand( 42 );
for ( $i = $have; $i < $target; $i++ ) {
	$title = ucfirst( $words[ mt_rand( 0, 19 ) ] ) . ' ' . $words[ mt_rand( 0, 19 ) ] . ' ' . $words[ mt_rand( 0, 19 ) ] . " $i";
	$pc = array( $cats[ mt_rand( 0, count( $cats ) - 1 ) ] ); if ( 0 === $i % 3 ) { $pc[] = $cats[ mt_rand( 0, count( $cats ) - 1 ) ]; }
	wp_insert_post( array( 'post_title' => $title, 'post_status' => 'publish', 'post_category' => $pc,
		'post_content' => str_repeat( $words[ mt_rand( 0, 19 ) ] . ' ' . $words[ mt_rand( 0, 19 ) ] . ' lorem ipsum dolor sit amet. ', 12 ),
		'post_date' => gmdate( 'Y-m-d H:i:s', time() - mt_rand( 0, 8 * 365 * 86400 ) ) ), false, false );
}
wp_suspend_cache_invalidation( false ); wp_defer_term_counting( false ); wp_update_term_count_now( $cats, 'category' );
printf( "generated up to %d published posts in %.1fs\n", (int) wp_count_posts( 'post' )->publish, microtime( true ) - $t0 );
