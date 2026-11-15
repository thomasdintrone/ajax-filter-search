<?php
require_once __DIR__ . '/class-afs-spike-rest.php';

/**
 * SPIKE 2: REST fragment + JSON meta (ETag, Cache-Control, object cache, signed config) at 10,000 posts.
 * Numbers are written to .cache/spike-rest-results.json. The object cache here is the in-memory default,
 * so "cached" timings show the logic cost; a persistent cache (Redis/Memcached) is not exercised.
 *
 * @group spike
 */
class RestFragmentSpikeTest extends AFS_Test_Case {

	const BULK = 10000;

	private static $results = array();

	public static function wpSetUpBeforeClass( $factory ) {
		parent::wpSetUpBeforeClass( $factory );
		global $wpdb;
		$cats  = array();
		foreach ( array( 'news', 'press-releases', 'events' ) as $slug ) {
			$cats[] = (int) get_category_by_slug( $slug )->term_taxonomy_id;
		}
		$words = array( 'report', 'update', 'notice', 'summary', 'guide', 'results', 'quarterly', 'preview' );
		$start = 5000;
		for ( $chunk = 0; $chunk < self::BULK; $chunk += 500 ) {
			$posts = array();
			$rels  = array();
			for ( $i = $chunk; $i < $chunk + 500; $i++ ) {
				$id    = $start + $i;
				$date  = gmdate( 'Y-m-d H:i:s', strtotime( '2022-01-01 00:00:00' ) + ( $i * 1100 ) );
				$title = ucfirst( $words[ $i % 8 ] ) . ' item ' . $i . ' ' . $words[ ( $i * 3 ) % 8 ];
				$posts[] = $wpdb->prepare( '(%d,1,%s,%s,%s,%s,%s,"publish","open","open","","%s","","",%s,%s,"","","post",0,0)', $id, $date, $date, str_repeat( 'Body text for the item. ', 12 ), $title, '', 'item-' . $i, $date, $date ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				$rels[]  = '(' . $id . ',' . $cats[ $i % 3 ] . ',0)';
				if ( 0 === $i % 4 ) {
					$rels[] = '(' . $id . ',' . $cats[ ( $i + 1 ) % 3 ] . ',0)';
				}
			}
			$wpdb->query( "INSERT INTO {$wpdb->posts} (ID,post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,post_modified,post_modified_gmt,post_content_filtered,guid,post_type,menu_order,post_parent) VALUES " . implode( ',', $posts ) ); // phpcs:ignore
			$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id,term_taxonomy_id,term_order) VALUES " . implode( ',', $rels ) ); // phpcs:ignore
		}
		wp_cache_flush();
	}

	public static function tearDownAfterClass(): void {
		$dir = '/repo/.cache';
		if ( is_dir( $dir ) ) {
			file_put_contents( $dir . '/spike-rest-results.json', wp_json_encode( self::$results, JSON_PRETTY_PRINT ) );
		}
		parent::tearDownAfterClass();
	}

	public function set_up() {
		parent::set_up();
		AFS_Spike_Rest::init();
		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $this->server );
	}

	private function get( $route, array $params = array(), array $headers = array() ) {
		$req = new WP_REST_Request( 'GET', $route );
		$req->set_query_params( $params );
		foreach ( $headers as $k => $v ) {
			$req->set_header( $k, $v );
		}
		return $this->server->dispatch( $req );
	}

	private function ms( callable $fn ) {
		$t = hrtime( true );
		$fn();
		return ( hrtime( true ) - $t ) / 1e6;
	}

	private static function p95( array $v ) {
		sort( $v );
		return $v[ (int) ceil( 0.95 * count( $v ) ) - 1 ];
	}

	private function states() {
		$out = array();
		foreach ( array( '', 'news', 'press-releases', 'events' ) as $t ) {
			foreach ( array( 0, 2023, 2024 ) as $y ) {
				foreach ( array( 0, 6 ) as $m ) {
					$out[] = array_filter( array( 'afs_term' => $t, 'afs_year' => $y, 'afs_month' => $m, 'posts_per_page' => 12 ) );
				}
			}
		}
		$out[] = array( 'afs_s' => 'quarterly', 'posts_per_page' => 12 );
		$out[] = array( 'afs_s' => 'report item', 'afs_page' => 3, 'posts_per_page' => 12 );
		$out[] = array( 'afs_page' => 40, 'posts_per_page' => 12 );
		$out[] = array( 'afs_term' => 'news', 'afs_s' => 'update', 'posts_per_page' => 12 );
		return $out;
	}

	public function test_payload_size_per_interaction() {
		$rows = array();
		foreach ( array( 10, 12, 24, 100 ) as $pp ) {
			$data  = $this->get( '/afs-spike/v1/results', array( 'posts_per_page' => $pp ) )->get_data();
			$json  = wp_json_encode( $data );
			$rows[ "per_page=$pp" ] = array( 'raw_bytes' => strlen( $json ), 'gzip_bytes' => strlen( gzencode( $json, 6 ) ), 'items' => substr_count( $data['html'], '<article' ) );
		}
		self::$results['payload (JSON incl. html + meta)'] = $rows;
		$this->assertLessThanOrEqual( 15 * 1024, $rows['per_page=12']['gzip_bytes'], 'A 12-item interaction must stay under 15 KB gzip' );
	}

	public function test_meta_shape_and_headers() {
		$res = $this->get( '/afs-spike/v1/results', array( 'posts_per_page' => 12, 'afs_page' => 2 ) );
		$d   = $res->get_data();
		$this->assertSame( 2, $d['meta']['page'] );
		$this->assertSame( 12, $d['meta']['per_page'] );
		$this->assertGreaterThan( 10000, $d['meta']['found'] );
		$h = $res->get_headers();
		$this->assertNotEmpty( $h['ETag'] );
		$this->assertStringContainsString( 'max-age=60', $h['Cache-Control'] );
		self::$results['meta + headers'] = array( 'found' => $d['meta']['found'], 'pages' => $d['meta']['pages'], 'headers' => $h );
	}

	public function test_timing_uncached_cached_and_304_at_10k_posts() {
		$uncached = array();
		foreach ( $this->states() as $p ) {
			wp_cache_flush();
			$uncached[] = $this->ms( function () use ( $p ) { $this->get( '/afs-spike/v1/results', $p ); } );
		}
		$cached = array();
		$p      = array( 'afs_term' => 'news', 'afs_year' => 2023, 'posts_per_page' => 12 );
		wp_cache_flush();
		$first = $this->get( '/afs-spike/v1/results', $p );
		$etag  = $first->get_headers()['ETag'];
		for ( $i = 0; $i < 30; $i++ ) {
			$cached[] = $this->ms( function () use ( $p ) { $this->get( '/afs-spike/v1/results', $p ); } );
		}
		$conditional = array();
		for ( $i = 0; $i < 30; $i++ ) {
			$conditional[] = $this->ms( function () use ( $p, $etag, &$status ) { $status = $this->get( '/afs-spike/v1/results', $p, array( 'If-None-Match' => $etag ) )->get_status(); } );
		}
		$this->assertSame( 304, $status );
		self::$results['timing ms at 10k posts (in-process, PHPUnit, in-memory object cache)'] = array(
			'uncached_states'  => count( $uncached ),
			'uncached_p95'     => round( self::p95( $uncached ), 1 ),
			'uncached_median'  => round( $uncached[ (int) ( count( $uncached ) / 2 ) ], 1 ),
			'uncached_max'     => round( max( $uncached ), 1 ),
			'cached_p95'       => round( self::p95( $cached ), 1 ),
			'conditional_304_p95' => round( self::p95( $conditional ), 1 ),
		);
		$this->assertLessThanOrEqual( 300, self::p95( $uncached ) );
		$this->assertLessThanOrEqual( 50, self::p95( $cached ) );
	}

	public function test_etag_changes_when_content_changes() {
		$p    = array( 'posts_per_page' => 12 );
		$one  = $this->get( '/afs-spike/v1/results', $p );
		$tag1 = $one->get_headers()['ETag'];
		$id   = self::factory()->post->create( array( 'post_title' => 'Fresh arrival zzz', 'post_status' => 'publish', 'post_date' => '2026-10-25 00:00:00' ) );
		$two  = $this->get( '/afs-spike/v1/results', $p, array( 'If-None-Match' => $tag1 ) );
		$this->assertSame( 200, $two->get_status(), 'Stale validator must not produce a 304 after a publish' );
		$this->assertStringContainsString( 'Fresh arrival zzz', $two->get_data()['html'] );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		$three = $this->get( '/afs-spike/v1/results', $p );
		$this->assertStringNotContainsString( 'Fresh arrival zzz', $three->get_data()['html'], 'Unpublishing must drop it from cached output' );
		self::$results['invalidation'] = array( 'publish_changes_etag' => true, 'unpublish_removes_item' => true );
	}

	public function test_two_feeds_get_separate_cache_entries() {
		$a = $this->get( '/afs-spike/v1/results', array( 'terms' => 'news', 'posts_per_page' => 5, 'instance' => 1 ) );
		$b = $this->get( '/afs-spike/v1/results', array( 'terms' => 'events', 'posts_per_page' => 7, 'instance' => 2 ) );
		$this->assertNotSame( $a->get_headers()['ETag'], $b->get_headers()['ETag'] );
		$this->assertSame( 5, substr_count( $a->get_data()['html'], '<article' ) );
		$this->assertSame( 7, substr_count( $b->get_data()['html'], '<article' ) );
		self::$results['multi-feed'] = array( 'isolated' => true );
	}

	public function test_signed_config() {
		$cfg = array( 'post_type' => 'post', 'post_tax' => 'category', 'terms' => 'news', 'posts_per_page' => '12', 'orderby' => 'date', 'order' => 'DESC' );
		$sig = AFS_Spike_Rest::sign( $cfg );

		$ok = $this->get( '/afs-spike/v1/results', $cfg + array( 'sig' => $sig ) );
		$this->assertSame( 200, $ok->get_status() );
		$this->assertTrue( $ok->get_data()['meta']['signed'] );

		$tampered = $this->get( '/afs-spike/v1/results', array_merge( $cfg, array( 'terms' => 'events', 'sig' => $sig ) ) );
		$this->assertSame( 403, $tampered->get_status(), 'Changing a signed field must be rejected' );

		$bigger = $this->get( '/afs-spike/v1/results', array_merge( $cfg, array( 'posts_per_page' => '100', 'sig' => $sig ) ) );
		$this->assertSame( 403, $bigger->get_status() );

		$state_ok = $this->get( '/afs-spike/v1/results', $cfg + array( 'sig' => $sig, 'afs_s' => 'quarterly', 'afs_page' => 2 ) );
		$this->assertSame( 200, $state_ok->get_status(), 'Visitor state (search, page) is not signed and must still work' );

		$legacy = $this->get( '/afs-spike/v1/results', array( 'post_type' => 'post' ) );
		$this->assertSame( 200, $legacy->get_status(), 'Unsigned legacy-style requests stay accepted (lenient mode)' );
		$this->assertFalse( $legacy->get_data()['meta']['signed'] );

		add_filter( 'salt', static function ( $salt, $scheme ) { return 'auth' === $scheme ? 'rotated-' . $salt : $salt; }, 10, 2 );
		$after_rotation = $this->get( '/afs-spike/v1/results', $cfg + array( 'sig' => $sig ) );
		$this->assertSame( 403, $after_rotation->get_status(), 'A salt rotation invalidates previously issued signatures' );

		self::$results['signed config'] = array(
			'valid_sig' => 200, 'tampered_terms' => 403, 'raised_per_page' => 403, 'visitor_state_unsigned_ok' => 200,
			'unsigned_lenient' => 200, 'after_salt_rotation_old_sig' => 403,
		);
	}

	public function test_existing_route_shape_is_unchanged() {
		$res = $this->get( '/afs/v1/results', array( 'posts_per_page' => 12 ) );
		$this->assertSame( array( 'found', 'html', 'pages' ), ( function ( $k ) { sort( $k ); return $k; } )( array_keys( $res->get_data() ) ) );
		$this->assertArrayNotHasKey( 'ETag', $res->get_headers(), 'The 2.0.0 route is untouched by the spike' );
		self::$results['legacy route /afs/v1/results'] = array( 'keys' => array_keys( $res->get_data() ) );
	}
}
