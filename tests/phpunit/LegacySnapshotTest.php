<?php
/**
 * Freezes the rendered output of every 2.0.0 entry point. Snapshots in tests/snapshots/2.0.0 are generated from
 * the v2.0.0 tag (bin/generate-snapshots.sh), never from the working tree.
 *
 * @group snapshot
 */
class LegacySnapshotTest extends AFS_Test_Case {

	/** Every shortcode form 1.x or 2.0.0 sites may use. */
	public function shortcode_provider() {
		$cases = array(
			'default'                  => '[ajax_filter_search]',
			'per-page-3'               => '[ajax_filter_search posts_per_page="3"]',
			'offset-2'                 => '[ajax_filter_search posts_per_page="3" offset="2"]',
			'filter-type-news'         => '[ajax_filter_search filter_type="news"]',
			'filter-type-two'          => '[ajax_filter_search filter_type="news,events"]',
			'filter-by-slugs'          => '[ajax_filter_search filter_by="press-releases,events"]',
			'terms-attr'               => '[ajax_filter_search terms="news"]',
			'filter-months'            => '[ajax_filter_search filter_months="3"]',
			'filter-years'             => '[ajax_filter_search filter_years="2025"]',
			'month-and-year'           => '[ajax_filter_search filter_months="3" filter_years="2024"]',
			'search-preset'            => '[ajax_filter_search search="report"]',
			'layout-grid'              => '[ajax_filter_search layout="grid"]',
			'order-title-asc'          => '[ajax_filter_search orderby="title" order="ASC" posts_per_page="5"]',
			'order-modified'           => '[ajax_filter_search orderby="modified" order="DESC" posts_per_page="5"]',
			'no-tabs'                  => '[ajax_filter_search show_tabs="false"]',
			'no-views'                 => '[ajax_filter_search show_views="false"]',
			'no-search'                => '[ajax_filter_search show_search="false"]',
			'no-months'                => '[ajax_filter_search show_months="false"]',
			'no-years'                 => '[ajax_filter_search show_years="false"]',
			'bare-minimum'             => '[ajax_filter_search show_tabs="false" show_views="false" show_search="false" show_months="false" show_years="false"]',
			'post-tax-none'            => '[ajax_filter_search post_tax="none"]',
			'post-type-page'           => '[ajax_filter_search post_type="page" post_tax="none"]',
			'unknown-post-type'        => '[ajax_filter_search post_type="does_not_exist"]',
			'single-term-no-tabs'      => '[ajax_filter_search terms="events"]',
			'legacy-filter-with-pdf'   => '[ajax_filter_search filter_withPDF="true"]',
			'afs-feed-default'         => '[afs_feed]',
			'afs-feed-attrs'           => '[afs_feed posts_per_page="3" filter_months="3"]',
			'afs-feed-type'            => '[afs_feed filter_type="news" posts_per_page="4"]',
		);
		foreach ( $cases as $name => $code ) {
			yield $name => array( $name, $code );
		}
	}

	/** @dataProvider shortcode_provider */
	public function test_shortcode_output( $name, $code ) {
		$this->assertMatchesSnapshot( 'shortcode/' . $name, $this->normalize( do_shortcode( $code ) ) );
	}

	public function test_two_feeds_on_one_page() {
		$out = do_shortcode( '[ajax_filter_search posts_per_page="3" layout="grid"] [ajax_filter_search terms="news,events" posts_per_page="2" show_search="false"]' );
		$this->assertMatchesSnapshot( 'page/two-feeds', $this->normalize( $out ) );
	}

	public function test_three_feeds_on_one_page_unique_ids_and_labels() {
		$out = do_shortcode( '[ajax_filter_search][ajax_filter_search][ajax_filter_search]' );
		$this->assertMatchesSnapshot( 'page/three-feeds', $this->normalize( $out ) );
	}

	/** Block attribute sets, rendered through the real block pipeline. */
	public function block_provider() {
		$cases = array(
			'default'          => array(),
			'per-page-4'       => array( 'perPage' => 4 ),
			'views-hidden'     => array( 'perPage' => 4, 'showViews' => 'hide' ),
			'tabs-hidden'      => array( 'showTabs' => 'hide' ),
			'terms'            => array( 'terms' => 'news,events' ),
			'grid-title-asc'   => array( 'layout' => 'grid', 'orderby' => 'title', 'order' => 'ASC', 'perPage' => 5 ),
			'toggles-off'      => array( 'showSearch' => false, 'showMonths' => false, 'showYears' => false ),
			'page-type'        => array( 'postType' => 'page', 'taxonomy' => 'none' ),
		);
		foreach ( $cases as $name => $attrs ) {
			yield $name => array( $name, $attrs );
		}
	}

	/** @dataProvider block_provider */
	public function test_block_output( $name, $attrs ) {
		$markup = '<!-- wp:ajax-filter-search/feed ' . wp_json_encode( (object) $attrs ) . ' /-->';
		$blocks = parse_blocks( $markup );
		$this->assertSame( 'ajax-filter-search/feed', $blocks[0]['blockName'] );
		$this->assertMatchesSnapshot( 'block/' . $name, $this->normalize( render_block( $blocks[0] ) ) );
	}

	/** Deep links read the first feed's state from the address bar and render it server-side. */
	public function deep_link_provider() {
		$cases = array(
			'term'           => array( 'afs_term' => 'news' ),
			'search'         => array( 'afs_s' => 'report' ),
			'year'           => array( 'afs_year' => '2025' ),
			'month-year'     => array( 'afs_month' => '3', 'afs_year' => '2024' ),
			'page-2'         => array( 'afs_page' => '2' ),
			'layout-grid'    => array( 'afs_layout' => 'grid' ),
			'combined'       => array( 'afs_term' => 'news', 'afs_s' => 'report', 'afs_year' => '2026', 'afs_page' => '1' ),
			'bad-values'     => array( 'afs_term' => '<b>x</b>', 'afs_month' => '99', 'afs_year' => 'abc', 'afs_page' => '-3', 'afs_layout' => 'zzz' ),
			'quoted-search'  => array( 'afs_s' => '"><script>alert(1)</script>' ),
		);
		foreach ( $cases as $name => $get ) {
			yield $name => array( $name, $get );
		}
	}

	/** @dataProvider deep_link_provider */
	public function test_deep_link_state( $name, $get ) {
		$_GET = $get;
		$this->assertMatchesSnapshot( 'deeplink/' . $name, $this->normalize( do_shortcode( '[ajax_filter_search posts_per_page="4"]' ) ) );
	}

	/** REST results for the states the front end can send. */
	public function rest_provider() {
		$base  = array( 'base_url' => 'http://example.org/sample-page/', 'instance' => 1 );
		$cases = array(
			'default'            => array(),
			'page-2-per-3'       => array( 'posts_per_page' => 3, 'afs_page' => 2 ),
			'term'               => array( 'afs_term' => 'news' ),
			'terms-restricted'   => array( 'terms' => 'news,events', 'afs_term' => 'events' ),
			'term-outside-set'   => array( 'terms' => 'news', 'afs_term' => 'events' ),
			'search'             => array( 'afs_s' => 'report' ),
			'search-no-match'    => array( 'afs_s' => 'zzzzqqq' ),
			'year'               => array( 'afs_year' => 2025 ),
			'month-year'         => array( 'afs_month' => 3, 'afs_year' => 2024 ),
			'combined'           => array( 'afs_term' => 'news', 'afs_s' => 'report', 'afs_year' => 2026 ),
			'title-asc'          => array( 'orderby' => 'title', 'order' => 'ASC', 'posts_per_page' => 5 ),
			'page-type'          => array( 'post_type' => 'page', 'post_tax' => 'none' ),
			'unknown-type'       => array( 'post_type' => 'revision' ),
			'unknown-tax'        => array( 'post_tax' => 'nav_menu' ),
			'instance-2'         => array( 'instance' => 2, 'posts_per_page' => 2 ),
			'last-page'          => array( 'posts_per_page' => 5, 'afs_page' => 3 ),
			'beyond-last-page'   => array( 'posts_per_page' => 5, 'afs_page' => 90 ),
			'markup-in-term'     => array( 'afs_term' => '<b>x</b>' ),
		);
		foreach ( $cases as $name => $params ) {
			yield $name => array( $name, array_merge( $base, $params ) );
		}
	}

	/** @dataProvider rest_provider */
	public function test_rest_results( $name, $params ) {
		$response = $this->rest( $params );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$snapshot = 'found=' . $data['found'] . "\npages=" . $data['pages'] . "\n---\n" . $this->normalize( $data['html'] );
		$this->assertMatchesSnapshot( 'rest/' . $name, $snapshot );
	}

	/** A 1.0.3 theme override (it uses FILTER_TYPE and AFSAdmin::afs_retrieve) must keep rendering the same. */
	public function test_1_0_3_theme_override() {
		$file = dirname( __DIR__ ) . '/fixtures/theme-overrides/1.0.3-default.php';
		add_filter(
			'afs_template_file',
			static function () use ( $file ) {
				return $file;
			}
		);
		$this->assertMatchesSnapshot( 'override/1.0.3-default', $this->normalize( do_shortcode( '[ajax_filter_search posts_per_page="4"]' ) ) );
	}

	public function test_legacy_compat_switched_off() {
		add_filter( 'afs_enable_legacy_compat', '__return_false' );
		$this->assertMatchesSnapshot( 'compat/off-default', $this->normalize( do_shortcode( '[ajax_filter_search posts_per_page="3"]' ) ) );
	}

	public function test_legacy_php_interfaces_exist() {
		foreach ( array( 'afs_feed', 'ajax_filter_search', 'AFSinit', 'get_the_selected_excerpt', 'return_template' ) as $fn ) {
			$this->assertTrue( function_exists( $fn ), "Missing legacy function $fn()" );
		}
		foreach ( array( 'AFSAdmin', 'AFSSettings' ) as $class ) {
			$this->assertTrue( class_exists( $class ), "Missing legacy class $class" );
		}
		foreach ( array( 'AFS_VERSION', 'AFS_RELEASE', 'AFS_CALLBACK', 'AFS_PLUGIN_ADMIN_URL', 'AFS_SUB', 'AFS_SETTINGS', 'AFS_MENU_SLUG' ) as $const ) {
			$this->assertTrue( defined( $const ), "Missing legacy constant $const" );
		}
		$this->assertSame( 'category', AFSAdmin::afs_retrieve( '_general_post_taxonomy' ) );
		$this->assertSame( 10, (int) AFSAdmin::afs_retrieve( '_general_posts_per_page' ) );
		$this->assertTrue( wp_script_is( 'afs-script-js', 'registered' ), 'Legacy script handle afs-script-js not registered' );
		$this->assertTrue( wp_style_is( 'afs-fontawesome', 'registered' ), 'Legacy style handle afs-fontawesome not registered' );
	}
}
