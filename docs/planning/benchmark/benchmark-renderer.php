<?php
// temp benchmark: times AFS_Renderer for representative requests (in-process, warm opcache)
global $wpdb; if ( ! defined( 'SAVEQUERIES' ) ) { define( 'SAVEQUERIES', true ); }
$cat = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ) )[0];
$scen = array(
 'page 1, no filters'            => array( array(), array() ),
 'keyword search "report"'       => array( array(), array( 's' => 'report' ) ),
 'one category (largest)'        => array( array(), array( 'term' => $cat->slug ) ),
 'month+year'                    => array( array(), array( 'month' => 3, 'year' => 2025 ) ),
 'deep page (page 40)'           => array( array(), array( 'paged' => 40 ) ),
 'per_page 100'                  => array( array( 'posts_per_page' => 100 ), array() ),
);
printf( "posts=%d  terms(category)=%d\n", wp_count_posts()->publish, wp_count_terms( array( 'taxonomy' => 'category' ) ) );
printf( "%-26s %9s %8s %8s %7s\n", 'scenario', 'ms(cold)', 'queries', 'db ms', 'found' );
foreach ( $scen as $name => $sc ) {
	$times = array(); $q = 0; $found = 0;
	for ( $i = 0; $i < 5; $i++ ) { wp_cache_flush(); $wpdb->queries = array(); $n0 = $wpdb->num_queries;
		$config = AFS_Renderer::normalize_config( $sc[0] ); $state = AFS_Renderer::normalize_state( $sc[1], $config );
		$t = microtime( true ); $r = AFS_Renderer::render_results( $config, $state, home_url( '/' ) ); $times[] = ( microtime( true ) - $t ) * 1000;
		$q = $wpdb->num_queries - $n0; $found = $r['found']; $dbt = 0; foreach ( $wpdb->queries as $row ) { $dbt += $row[1] * 1000; } }
	sort( $times ); printf( "%-26s %9.1f %8d %8.1f %7d\n", $name, $times[2], $q, $dbt, $found );
}
// widget shell (tabs + year list) cold vs warm
foreach ( array( 'widget (tabs+years) cold', 'widget (tabs+years) warm' ) as $i => $label ) {
	if ( 0 === $i ) { wp_cache_flush(); }
	$wpdb->queries = array(); $n0 = $wpdb->num_queries; $config = AFS_Renderer::normalize_config( array() ); $state = AFS_Renderer::normalize_state( array(), $config );
	$t = microtime( true ); AFS_Renderer::render_widget( $config, $state ); printf( "%-26s %9.1f %8d\n", $label, ( microtime( true ) - $t ) * 1000, $wpdb->num_queries - $n0 );
}
