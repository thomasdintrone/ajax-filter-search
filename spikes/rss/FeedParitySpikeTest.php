<?php
require_once __DIR__ . '/class-afs-spike-feed.php';

/**
 * SPIKE 3: can core feed URLs express every AFS state, or do we need our own endpoint?
 * Parity = the same posts in the same order. Results are also written to .cache/spike-rss-results.json.
 *
 * @group spike
 */
class FeedParitySpikeTest extends AFS_Test_Case {

	private static $results = array();

	public function set_up() {
		parent::set_up();
		update_option( 'posts_per_rss', 100 );
		update_option( 'permalink_structure', '' );
	}

	public static function tearDownAfterClass(): void {
		$dir = '/repo/.cache';
		if ( is_dir( $dir ) ) {
			file_put_contents( $dir . '/spike-rss-results.json', wp_json_encode( self::$results, JSON_PRETTY_PRINT ) );
		}
		parent::tearDownAfterClass();
	}

	private function afs_ids( array $raw_config, array $raw_state ) {
		$config = AFS_Renderer::normalize_config( array_merge( array( 'posts_per_page' => 100 ), $raw_config ) );
		$state  = AFS_Renderer::normalize_state( $raw_state, $config );
		return array( $config, $state, wp_list_pluck( AFS_Renderer::query( $config, $state )->posts, 'ID' ) );
	}

	/** IDs the main query returns for a core feed URL. */
	private function core_feed_ids( $query_string ) {
		$this->go_to( home_url( '/?feed=rss2&' . $query_string ) );
		$this->assertTrue( is_feed(), 'Not recognised as a feed: ' . $query_string );
		return wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
	}

	/** States an AFS widget can produce today, with the core feed URL that should express each. */
	public function supported_provider() {
		$terms  = array( '' => '', 'news' => 'category_name=news', 'press-releases' => 'category_name=press-releases', 'events' => 'category_name=events' );
		$years  = array( 0 => '', 2024 => 'year=2024', 2025 => 'year=2025', 2026 => 'year=2026' );
		$months = array( 0 => '', 3 => 'monthnum=3' );
		$search = array( '' => '', 'report' => 's=report', 'quarterly' => 's=quarterly' );
		$i      = 0;
		foreach ( $terms as $t => $tq ) {
			foreach ( $years as $y => $yq ) {
				foreach ( $months as $m => $mq ) {
					foreach ( $search as $s => $sq ) {
						// Deterministic sample of the 96 combinations (every 3rd, offset by 1) plus the single-filter edges.
						if ( ( 0 === $i % 3 ) || ( 1 === ( ( $t ? 1 : 0 ) + ( $y ? 1 : 0 ) + ( $m ? 1 : 0 ) + ( $s ? 1 : 0 ) ) ) ) {
							$core = implode( '&', array_filter( array( $tq, $yq, $mq, $sq, 'orderby=date', 'order=DESC' ) ) );
							yield "t=$t y=$y m=$m s=$s" => array( array( 'term' => $t, 'year' => $y, 'month' => $m, 's' => $s ), $core );
						}
						++$i;
					}
				}
			}
		}
	}

	/** @dataProvider supported_provider */
	public function test_core_feed_url_matches_afs_results( array $state, $core_query ) {
		list( , , $afs ) = $this->afs_ids( array(), $state );
		$core            = $this->core_feed_ids( $core_query );
		self::$results[ 'supported: ' . $core_query ] = array( 'afs' => $afs, 'core' => $core, 'match' => $afs === $core );
		$this->assertSame( $afs, $core, 'Core feed differs from AFS for ' . $core_query );
	}

	public function test_restricted_term_set_matches_core_or_semantics() {
		list( , , $afs ) = $this->afs_ids( array( 'terms' => 'news,events' ), array() );
		$core            = $this->core_feed_ids( 'category_name=news,events&orderby=date&order=DESC' );
		self::$results['terms=news,events (OR)'] = array( 'afs' => $afs, 'core' => $core, 'match' => $afs === $core );
		$this->assertSame( $afs, $core );
	}

	public function test_title_ordering_and_paging_can_be_expressed() {
		list( , , $afs ) = $this->afs_ids( array( 'orderby' => 'title', 'order' => 'ASC', 'posts_per_page' => 5 ), array( 'paged' => 2 ) );
		update_option( 'posts_per_rss', 5 );
		$core = $this->core_feed_ids( 'orderby=title&order=ASC&paged=2' );
		self::$results['title asc, 5 per page, page 2'] = array( 'afs' => $afs, 'core' => $core, 'match' => $afs === $core );
		$this->assertSame( $afs, $core );
	}

	/** Where core feed URLs cannot follow AFS: record, do not fail. */
	public function test_record_limits_of_core_feed_urls() {
		// 1. A site's afs_query_args filter changes AFS results but not a core feed.
		add_filter(
			'afs_query_args',
			static function ( $args ) {
				$args['post__not_in'] = array( 1001 );
				return $args;
			}
		);
		list( , , $afs ) = $this->afs_ids( array(), array() );
		$core            = $this->core_feed_ids( 'orderby=date&order=DESC' );
		self::$results['limit: afs_query_args customization'] = array( 'match' => $afs === $core, 'afs_has_1001' => in_array( 1001, $afs, true ), 'core_has_1001' => in_array( 1001, $core, true ) );
		$this->assertNotSame( $afs, $core, 'Expected divergence when a site customizes the AFS query' );
		remove_all_filters( 'afs_query_args' );

		// 2. post_type is not honoured for the built-in page type in a feed URL.
		$types = array_unique( wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_type' ) );
		$this->go_to( home_url( '/?feed=rss2&post_type=page' ) );
		$page_types = array_values( array_unique( wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_type' ) ) );
		self::$results['limit: post_type=page in feed URL'] = array( 'result_post_types' => $page_types, 'is_post_type_archive' => is_post_type_archive(), 'found' => count( $GLOBALS['wp_query']->posts ) );

		// 3. Feed length comes from Settings > Reading (posts_per_rss), not from the AFS per-page value.
		update_option( 'posts_per_rss', 3 );
		$core3 = $this->core_feed_ids( 'orderby=date&order=DESC' );
		self::$results['limit: feed length follows posts_per_rss'] = array( 'items' => count( $core3 ) );
		$this->assertCount( 3, $core3 );
		$this->assertNotEmpty( $types );
	}

	public function test_prototype_endpoint_parity_validity_and_escaping() {
		$cases = array(
			array( array(), array() ),
			array( array(), array( 'term' => 'news' ) ),
			array( array(), array( 'term' => 'events', 'year' => 2026 ) ),
			array( array(), array( 's' => 'quarterly' ) ),
			array( array( 'terms' => 'news,events' ), array( 'month' => 3 ) ),
			array( array( 'orderby' => 'title', 'order' => 'ASC' ), array() ),
		);
		foreach ( $cases as $n => $c ) {
			list( $config, $state, $ids ) = $this->afs_ids( $c[0], $c[1] );
			$xml                          = AFS_Spike_Feed::xml( $config, $state );
			$guids                        = AFS_Spike_Feed::guids( $xml );
			$this->assertNotNull( $guids, "Case $n is not well-formed XML" );
			$expected = array_map( 'get_the_guid', $ids );
			$this->assertSame( $expected, $guids, "Case $n GUID order differs from AFS results" );
		}
		self::$results['prototype endpoint: 6 states, XML well-formed, GUIDs equal AFS results'] = array( 'match' => true );
	}

	public function test_prototype_endpoint_published_only_and_afs_filter_applies() {
		list( $config, $state ) = $this->afs_ids( array(), array( 's' => 'UNPUB' ) );
		$xml                    = AFS_Spike_Feed::xml( $config, $state );
		foreach ( array( 'UNPUB-draft', 'UNPUB-pending', 'UNPUB-future', 'UNPUB-private', 'BODYMARK' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $xml );
		}
		add_filter(
			'afs_query_args',
			static function ( $args ) {
				$args['post__not_in'] = array( 1001 );
				return $args;
			}
		);
		list( $config, $state ) = $this->afs_ids( array(), array() );
		$this->assertStringNotContainsString( 'Alpha report one', AFS_Spike_Feed::xml( $config, $state ), 'The AFS query filter must apply to the endpoint' );
		self::$results['prototype endpoint: published only, afs_query_args applied'] = array( 'match' => true );
	}

	public function test_prototype_endpoint_escapes_special_characters() {
		$id = self::factory()->post->create( array( 'post_title' => 'Tom & Jerry <b>"quoted"</b> O\'Brien', 'post_status' => 'publish', 'post_date' => '2026-10-20 10:00:00', 'post_content' => 'x ]]> y' ) );
		list( $config, $state ) = $this->afs_ids( array(), array( 's' => 'Jerry' ) );
		$xml                    = AFS_Spike_Feed::xml( $config, $state );
		$doc                    = new DOMDocument();
		$this->assertTrue( @$doc->loadXML( $xml ), 'Special characters must not break the XML' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		// Core writes & as the numeric entity &#038;, which parses to the same character, so compare parsed text.
		$titles = array();
		foreach ( $doc->getElementsByTagName( 'item' ) as $item ) {
			$titles[] = $item->getElementsByTagName( 'title' )->item( 0 )->textContent;
		}
		$this->assertContains( 'Tom & Jerry “quoted” O’Brien', $titles );
		$this->assertStringNotContainsString( '<b>', str_replace( array( '<![CDATA[', ']]>' ), '', strstr( $xml, '<item>' ) ) ?: '' );
		wp_delete_post( $id, true );
		self::$results['prototype endpoint: & < > quotes and ]]> in titles/content keep XML valid'] = array( 'match' => true );
	}
}
