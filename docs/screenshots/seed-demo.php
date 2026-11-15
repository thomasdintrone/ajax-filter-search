<?php
/**
 * Dev only: a small demo newsroom (fictional content) for the WordPress.org screenshots.
 * Run on a fresh stack:  wp eval-file wp-content/plugins/ajax-filter-search/docs/screenshots/seed-demo.php
 *
 * @package ajax-filter-search
 */

update_option( 'blogname', 'Example Newsroom' );
update_option( 'blogdescription', '' );
update_option( 'timezone_string', 'UTC' );
update_option( 'permalink_structure', '/%postname%/' );
foreach ( array( 1, 2 ) as $id ) {
	wp_delete_post( $id, true ); // The default post and page.
}

$cats = array();
foreach ( array( 'News' => 'news', 'Press Releases' => 'press-releases', 'Events' => 'events' ) as $name => $slug ) {
	$term         = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	$cats[ $slug ] = is_wp_error( $term ) ? get_term_by( 'slug', $slug, 'category' )->term_id : $term['term_id'];
}

$items = array(
	'news'           => array(
		'Riverside library extends weekend hours',
		'City council approves new bike lanes on Harbor Street',
		'Local bakery wins regional sustainability award',
		'School garden program expands to six more campuses',
		'Ferry service adds evening crossings this summer',
		'Community solar project reaches full subscription',
		'Public art trail opens along the waterfront',
		'Neighborhood clean-up draws record volunteers',
		'Transit agency introduces contactless fares',
		'Winter shelter network opens early this year',
		'Farmers market launches weekly recipe series',
		'Harbor dredging project completes ahead of schedule',
	),
	'press-releases' => array(
		'Example Newsroom announces 2026 community grants',
		'Quarterly results: membership grows 12 percent',
		'New partnership brings free Wi-Fi to public parks',
		'Annual report now available online',
		'Appointment of a new director of programs',
		'Statement on the proposed harbor development',
		'Call for proposals: neighborhood storytelling fund',
		'Launch of the open data portal',
		'Update to our privacy and accessibility commitments',
		'Winter schedule and holiday closures',
		'Volunteer of the year announced',
		'Joint statement with regional libraries',
	),
	'events'         => array(
		'Harbor Light Festival returns to the waterfront',
		'Open house at the community workshop',
		'Spring book fair at the central library',
		'Town hall: transit improvements',
		'Seed swap and planting day',
		'Evening concert series opens in the park',
		'Resume and career help drop-in',
		'Photography walk along the river',
		'Winter lights parade',
		'Youth coding day',
		'Neighborhood potluck and games',
		'Annual membership meeting',
	),
);

$excerpts = array(
	'A short summary of what happened and what comes next, with the key dates and where to find more details.',
	'Read the full story for the background, who is involved and how residents can take part.',
	'Details, schedule and contact information are available on the page, along with related coverage.',
);

$i    = 0;
$base = strtotime( '2026-11-10 10:00:00 UTC' );
for ( $row = 0; $row < 12; $row++ ) {
	foreach ( $items as $slug => $titles ) {
		$date = gmdate( 'Y-m-d H:i:s', $base - ( $i * 37 * DAY_IN_SECONDS ) + ( $i % 5 ) * 3600 );
		wp_insert_post(
			array(
				'post_title'    => $titles[ $row ],
				'post_status'   => 'publish',
				'post_date'     => $date,
				'post_date_gmt' => $date,
				'post_excerpt'  => $excerpts[ $i % 3 ],
				'post_content'  => $excerpts[ $i % 3 ],
				'post_category' => array( $cats[ $slug ] ),
			)
		);
		++$i;
	}
}

$pages = array(
	'Press Room' => '[ajax_filter_search posts_per_page="8"]',
	'Newsroom'   => "<h2>Latest news and events</h2>\n[ajax_filter_search posts_per_page=\"4\" terms=\"news,events\"]\n<h2>Press releases</h2>\n[ajax_filter_search posts_per_page=\"3\" terms=\"press-releases\" show_search=\"false\" show_views=\"false\"]",
	'Resources'  => '[ajax_filter_search layout="grid" posts_per_page="6"]',
	'Archive'    => '[ajax_filter_search posts_per_page="6" show_sort="true" show_chips="true"]',
	'About'      => 'Example Newsroom is a fictional site used to show Ajax Filter Search.',
);
foreach ( $pages as $title => $content ) {
	wp_insert_post( array( 'post_title' => $title, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content ) );
}
// A draft with the block, for the editor screenshot (a draft stays out of the site menu).
wp_insert_post(
	array(
		'post_title'   => 'Block demo',
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_content' => '<!-- wp:ajax-filter-search/feed {"perPage":6,"showSort":"show","showChips":"show"} /-->',
	)
);

flush_rewrite_rules();
echo "demo content ready\n";
