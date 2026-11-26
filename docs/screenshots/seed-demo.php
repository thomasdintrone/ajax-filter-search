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
		$post_id = wp_insert_post(
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
		if ( 3 !== $i % 4 ) { // Every fourth post has no image, so the placeholder shows too.
			afs_demo_cover( $post_id, $i );
		}
		++$i;
	}
}

// Cover art for the designs that show images: soft gradients with a few circles (generated, no photos, no licences).
require_once ABSPATH . 'wp-admin/includes/image.php';
function afs_demo_cover( $post_id, $n ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return;
	}
	$palettes = array(
		array( array( 245, 158, 11 ), array( 239, 68, 68 ) ),
		array( array( 16, 185, 129 ), array( 14, 165, 233 ) ),
		array( array( 99, 102, 241 ), array( 236, 72, 153 ) ),
		array( array( 14, 165, 233 ), array( 34, 197, 94 ) ),
		array( array( 139, 92, 246 ), array( 59, 130, 246 ) ),
		array( array( 251, 146, 60 ), array( 250, 204, 21 ) ),
	);
	list( $a, $b ) = $palettes[ $n % count( $palettes ) ];
	$w  = 1200;
	$h  = 750;
	$im = imagecreatetruecolor( $w, $h );
	imagealphablending( $im, true );
	for ( $y = 0; $y < $h; $y += 2 ) {
		for ( $x = 0; $x < $w; $x += 2 ) {
			$t = ( $x / $w * 0.7 + $y / $h * 0.3 );
			imagefilledrectangle( $im, $x, $y, $x + 1, $y + 1, imagecolorallocate( $im, (int) ( $a[0] + ( $b[0] - $a[0] ) * $t ), (int) ( $a[1] + ( $b[1] - $a[1] ) * $t ), (int) ( $a[2] + ( $b[2] - $a[2] ) * $t ) ) );
		}
	}
	mt_srand( $n * 7919 );
	for ( $c = 0; $c < 4; $c++ ) {
		$circle = imagecolorallocatealpha( $im, 255, 255, 255, mt_rand( 100, 118 ) );
		$d      = mt_rand( 160, 520 );
		imagefilledellipse( $im, mt_rand( 0, $w ), mt_rand( 0, $h ), $d, $d, $circle );
	}
	$up   = wp_upload_dir();
	$file = $up['path'] . '/demo-cover-' . $post_id . '.jpg';
	imagejpeg( $im, $file, 86 );
	imagedestroy( $im );
	$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Cover ' . $post_id, 'post_status' => 'inherit' ), $file, $post_id );
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	set_post_thumbnail( $post_id, $att );
}

$pages = array(
	'Press Room' => '[ajax_filter_search posts_per_page="8"]',
	'Newsroom'   => "<h2>Latest news and events</h2>\n[ajax_filter_search posts_per_page=\"4\" terms=\"news,events\"]\n<h2>Press releases</h2>\n[ajax_filter_search posts_per_page=\"3\" terms=\"press-releases\" show_search=\"false\" show_views=\"false\"]",
	'Resources'  => '[ajax_filter_search layout="grid" posts_per_page="6"]',
	'Archive'    => '[ajax_filter_search posts_per_page="6" show_sort="true" show_chips="true"]',
	'Classic'    => '[ajax_filter_search design="classic" posts_per_page="5"]',
	'About'      => 'Example Newsroom is a fictional site used to show Ajax Filter Search.',
	// The new designs sit in a wide group so they get room for three columns.
	'Editorial'  => '<!-- wp:group {"align":"wide"} --><div class="wp-block-group alignwide"><!-- wp:ajax-filter-search/feed {"design":"editorial","perPage":6,"columns":3,"showSort":"show","showChips":"show"} /--></div><!-- /wp:group -->',
	'Library'    => '<!-- wp:group {"align":"wide"} --><div class="wp-block-group alignwide"><!-- wp:ajax-filter-search/feed {"design":"library","perPage":8,"showSort":"show","showChips":"show"} /--></div><!-- /wp:group -->',
	'Dark'       => '<!-- wp:group {"align":"wide","style":{"color":{"background":"#0f1420","text":"#eef1f7"},"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"2rem","right":"2rem"}},"border":{"radius":"16px"}},"layout":{"type":"default"}} --><div class="wp-block-group alignwide has-text-color has-background" style="border-radius:16px;color:#eef1f7;background-color:#0f1420;padding-top:2rem;padding-right:2rem;padding-bottom:2rem;padding-left:2rem"><!-- wp:ajax-filter-search/feed {"design":"editorial","perPage":6,"columns":3,"showSort":"show","showChips":"show"} /--></div><!-- /wp:group -->',
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
		'post_content' => '<!-- wp:ajax-filter-search/feed {"design":"editorial","perPage":6,"columns":3,"terms":"news,events","showSort":"show","showChips":"show"} /-->',
	)
);

flush_rewrite_rules();
echo "demo content ready\n";
