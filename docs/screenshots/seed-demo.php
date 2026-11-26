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

// Cover photos for the designs that show images: free Unsplash photographs, matched to articles by title (see photos.json and
// CREDITS.md; download them first with `node docs/screenshots/fetch-photos.mjs`). An article without one shows the missing-image tile.
require_once ABSPATH . 'wp-admin/includes/image.php';
$afs_demo_photos = array();
$afs_demo_list   = json_decode( (string) file_get_contents( __DIR__ . '/photos.json' ), true );
foreach ( isset( $afs_demo_list['photos'] ) ? $afs_demo_list['photos'] : array() as $photo ) {
	$afs_demo_photos[ $photo['title'] ] = __DIR__ . '/photos/' . $photo['id'] . '.jpg';
}
function afs_demo_cover( $post_id, $source ) {
	if ( ! is_readable( $source ) ) {
		return;
	}
	$up   = wp_upload_dir();
	$file = $up['path'] . '/demo-photo-' . $post_id . '.jpg';
	copy( $source, $file );
	$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Photo ' . $post_id, 'post_status' => 'inherit' ), $file, $post_id );
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	set_post_thumbnail( $post_id, $att );
}

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
		if ( isset( $afs_demo_photos[ $titles[ $row ] ] ) ) {
			afs_demo_cover( $post_id, $afs_demo_photos[ $titles[ $row ] ] );
		}
		++$i;
	}
}

// Pages for the screenshots. Each puts its heading inside a wide group and uses the theme's "no title" template, so the title lines
// up with the feed and nothing is pushed down the page (a demo-site setting, not plugin CSS).
$afs_h = static function ( $text, $level = 1, $size = '2rem' ) {
	return '<!-- wp:heading {"level":' . $level . ',"style":{"typography":{"fontSize":"' . $size . '"},"spacing":{"margin":{"top":"0","bottom":"1rem"}}}} --><h' . $level . ' class="wp-block-heading" style="margin-top:0;margin-bottom:1rem;font-size:' . $size . '">' . $text . '</h' . $level . '><!-- /wp:heading -->';
};
$afs_wide = static function ( $inner ) {
	return '<!-- wp:group {"align":"wide","layout":{"type":"default"}} --><div class="wp-block-group alignwide">' . $inner . '</div><!-- /wp:group -->';
};
$afs_dark = static function ( $inner ) {
	return '<!-- wp:group {"align":"wide","style":{"color":{"background":"#0f1420","text":"#eef1f7"},"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"2rem","right":"2rem"}},"border":{"radius":"16px"}},"layout":{"type":"default"}} --><div class="wp-block-group alignwide has-text-color has-background" style="border-radius:16px;color:#eef1f7;background-color:#0f1420;padding-top:2rem;padding-right:2rem;padding-bottom:2rem;padding-left:2rem">' . $inner . '</div><!-- /wp:group -->';
};
$afs_gap   = '<!-- wp:spacer {"height":"2.5rem"} --><div style="height:2.5rem" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
$afs_block = static function ( $attrs ) {
	return '<!-- wp:ajax-filter-search/feed ' . wp_json_encode( $attrs ) . ' /-->';
};

$pages = array(
	'Editorial' => $afs_wide( $afs_h( 'Newsroom' ) . $afs_block( array( 'design' => 'editorial', 'perPage' => 6, 'columns' => 3, 'showSort' => 'show', 'showChips' => 'show' ) ) ),
	'Library'   => $afs_wide( $afs_h( 'Reports and resources' ) . $afs_block( array( 'design' => 'library', 'perPage' => 8, 'showSort' => 'show', 'showChips' => 'show' ) ) ),
	'Dark'      => $afs_dark( $afs_h( 'Newsroom' ) . $afs_block( array( 'design' => 'editorial', 'perPage' => 6, 'columns' => 3, 'showSort' => 'show', 'showChips' => 'show' ) ) ),
	// Two feeds on one page, each with its own heading, design and filters.
	'Newsroom'  => $afs_wide(
		$afs_h( 'Newsroom' )
		. $afs_h( 'Latest stories', 2, '1.35rem' )
		. $afs_block( array( 'design' => 'editorial', 'perPage' => 3, 'columns' => 3, 'terms' => 'news,events', 'showChips' => 'show', 'showViews' => 'hide' ) )
		. $afs_gap
		. $afs_h( 'Press releases', 2, '1.35rem' )
		. $afs_block( array( 'design' => 'modern', 'perPage' => 3, 'terms' => 'press-releases', 'showTabs' => 'hide', 'showChips' => 'show', 'showViews' => 'hide' ) )
	),
	'Classic'   => $afs_wide( $afs_h( 'Press room' ) . $afs_block( array( 'design' => 'classic', 'perPage' => 5 ) ) ),
	'About'     => 'Example Newsroom is a fictional site used to show Ajax Filter Search.',
);
foreach ( $pages as $title => $content ) {
	wp_insert_post(
		array(
			'post_title'   => $title,
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => $content,
			'meta_input'   => array( '_wp_page_template' => 'page-no-title' ),
		)
	);
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
