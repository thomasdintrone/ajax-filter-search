<?php
/**
 * REST response meta, cache validators, the object cache and cached tab terms (Sprint 1, E1 and E2).
 * The frozen 2.0.0 snapshots only read html, found and pages, so these additions are tested here.
 */
class RestCachingTest extends AFS_Test_Case {

	public function tear_down() {
		remove_all_filters( 'afs_rest_cacheable' );
		remove_all_filters( 'afs_rest_cache_control' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function get( array $params = array(), array $headers = array() ) {
		$request = new WP_REST_Request( 'GET', '/afs/v1/results' );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		foreach ( $headers as $k => $v ) {
			$request->set_header( $k, $v );
		}
		return $this->server->dispatch( $request );
	}

	public function test_meta_describes_the_result_and_the_2_0_0_keys_are_unchanged() {
		$data = $this->get( array( 'posts_per_page' => 5, 'afs_page' => 2, 'afs_term' => 'news', 'afs_year' => 2024 ) )->get_data();
		$this->assertSame( $data['found'], $data['meta']['found'] );
		$this->assertSame( $data['pages'], $data['meta']['pages'] );
		$this->assertSame( 2, $data['meta']['page'] );
		$this->assertSame( 5, $data['meta']['per_page'] );
		$this->assertSame( 'news', $data['meta']['state']['term'] );
		$this->assertSame( 2024, $data['meta']['state']['year'] );
		$this->assertIsString( $data['html'] );
	}

	public function test_anonymous_response_has_validators_and_cache_control() {
		$headers = $this->get( array( 'posts_per_page' => 5 ) )->get_headers();
		$this->assertMatchesRegularExpression( '/^"[a-f0-9]{32}"$/', $headers['ETag'] );
		$this->assertStringContainsString( 'public', $headers['Cache-Control'] );
		$this->assertStringContainsString( 'max-age=60', $headers['Cache-Control'] );
	}

	public function test_matching_validator_returns_304_without_running_the_query() {
		$etag = $this->get( array( 'posts_per_page' => 5 ) )->get_headers()['ETag'];

		$queries = array();
		add_filter( 'posts_request', static function ( $sql ) use ( &$queries ) { $queries[] = $sql; return $sql; } );
		$res = $this->get( array( 'posts_per_page' => 5 ), array( 'If-None-Match' => $etag ) );
		$this->assertSame( 304, $res->get_status() );
		$this->assertNull( $res->get_data() );
		$this->assertSame( $etag, $res->get_headers()['ETag'] );
		$this->assertSame( array(), $queries, 'A 304 must not query posts' );
		remove_all_filters( 'posts_request' );
	}

	public function test_a_304_is_served_with_no_body() {
		$res = new WP_REST_Response( null, 304 );
		$req = new WP_REST_Request( 'GET', '/afs/v1/results' );
		$this->assertTrue( AFS_REST::serve_not_modified( false, $res, $req ) );
		$this->assertFalse( AFS_REST::serve_not_modified( false, new WP_REST_Response( array(), 200 ), $req ) );
		$this->assertFalse( AFS_REST::serve_not_modified( false, $res, new WP_REST_Request( 'GET', '/wp/v2/posts' ) ), 'Other routes are not affected' );
	}

	public function test_second_identical_request_is_served_from_the_object_cache() {
		$this->get( array( 'posts_per_page' => 5, 'afs_term' => 'events' ) );
		$queries = array();
		add_filter( 'posts_request', static function ( $sql ) use ( &$queries ) { $queries[] = $sql; return $sql; } );
		$again = $this->get( array( 'posts_per_page' => 5, 'afs_term' => 'events' ) )->get_data();
		remove_all_filters( 'posts_request' );
		$this->assertSame( array(), $queries, 'The repeat must not query posts' );
		$this->assertNotEmpty( $again['html'] );
	}

	public function test_publishing_and_unpublishing_invalidate_validators_and_output() {
		$one  = $this->get( array( 'posts_per_page' => 12 ) );
		$etag = $one->get_headers()['ETag'];

		$id  = self::factory()->post->create( array( 'post_title' => 'Cache bust arrival', 'post_status' => 'publish', 'post_date' => '2026-10-25 00:00:00' ) );
		AFS_Cache::flush(); // What the shutdown hook does at the end of the request that changed content.
		$two = $this->get( array( 'posts_per_page' => 12 ), array( 'If-None-Match' => $etag ) );
		$this->assertSame( 200, $two->get_status(), 'A stale validator must not give a 304 after a publish' );
		$this->assertNotSame( $etag, $two->get_headers()['ETag'] );
		$this->assertStringContainsString( 'Cache bust arrival', $two->get_data()['html'] );

		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		AFS_Cache::flush();
		$three = $this->get( array( 'posts_per_page' => 12 ) );
		$this->assertStringNotContainsString( 'Cache bust arrival', $three->get_data()['html'], 'Unpublishing must drop it from cached output' );
	}

	public function test_signed_in_requests_are_never_cached_or_given_validators() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$res = $this->get( array( 'posts_per_page' => 5 ) );
		$this->assertArrayNotHasKey( 'ETag', $res->get_headers() );
		$this->assertStringContainsString( 'private', $res->get_headers()['Cache-Control'] );

		$queries = array();
		add_filter( 'posts_request', static function ( $sql ) use ( &$queries ) { $queries[] = $sql; return $sql; } );
		$this->get( array( 'posts_per_page' => 5 ) );
		remove_all_filters( 'posts_request' );
		$this->assertNotEmpty( $queries, 'A signed-in repeat still runs the query (nothing is stored for it)' );
	}

	public function test_a_site_can_opt_out_of_caching_and_change_the_header() {
		add_filter( 'afs_rest_cacheable', '__return_false' );
		$res = $this->get( array( 'posts_per_page' => 5 ) );
		$this->assertArrayNotHasKey( 'ETag', $res->get_headers() );
		remove_all_filters( 'afs_rest_cacheable' );

		add_filter( 'afs_rest_cache_control', static function () { return 'public, max-age=5'; } );
		$this->assertSame( 'public, max-age=5', $this->get( array( 'posts_per_page' => 6 ) )->get_headers()['Cache-Control'] );
	}

	public function test_different_feeds_and_states_never_share_a_cache_entry() {
		$a = $this->get( array( 'terms' => 'news', 'posts_per_page' => 5, 'instance' => 1 ) );
		$b = $this->get( array( 'terms' => 'events', 'posts_per_page' => 3, 'instance' => 2 ) );
		$this->assertNotSame( $a->get_headers()['ETag'], $b->get_headers()['ETag'] );
		$this->assertSame( 5, substr_count( $a->get_data()['html'], '<article' ) );
		$this->assertSame( 3, substr_count( $b->get_data()['html'], '<article' ) );
	}

	public function test_tab_terms_are_cached_but_a_new_category_appears_at_once() {
		$render = static function () {
			return AFS_Renderer::render_widget( AFS_Renderer::normalize_config( array() ), AFS_Renderer::normalize_state( array(), AFS_Renderer::normalize_config( array() ) ) );
		};
		$this->assertStringNotContainsString( 'Brand New Tab', $render() );
		// Empty terms are hidden by get_terms(), so the new category needs a published post to show as a tab.
		$cat = self::factory()->category->create( array( 'name' => 'Brand New Tab', 'slug' => 'brand-new-tab' ) );
		self::factory()->post->create( array( 'post_status' => 'publish', 'post_category' => array( $cat ), 'post_date' => '2026-10-26 00:00:00' ) );
		$this->assertStringContainsString( 'Brand New Tab', $render(), 'Adding a term must invalidate the cached tab list' );
	}

	/** The bug real HTTP found: core's last_changed is regenerated per request without a persistent object cache. */
	public function test_validator_is_stable_across_requests_when_nothing_changed() {
		$first = $this->get( array( 'posts_per_page' => 5 ) )->get_headers()['ETag'];
		wp_cache_flush(); // A new request on a site without a persistent object cache starts with an empty cache.
		$second = $this->get( array( 'posts_per_page' => 5 ) )->get_headers()['ETag'];
		$this->assertSame( $first, $second );
		$this->assertSame( 304, $this->get( array( 'posts_per_page' => 5 ), array( 'If-None-Match' => $first ) )->get_status() );
	}

	public function test_counter_is_bumped_once_per_request_and_only_after_a_relevant_change() {
		AFS_Cache::flush();
		$before = AFS_Cache::version();
		AFS_Cache::flush();
		$this->assertSame( $before, AFS_Cache::version(), 'Nothing changed, nothing written' );

		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::factory()->category->create( array( 'name' => 'Counter test' ) );
		update_option( AFS_SETTINGS, array( 'afs_general_posts_per_page' => 9 ) );
		AFS_Cache::flush();
		$after = AFS_Cache::version();
		$this->assertNotSame( $before, $after );
		AFS_Cache::flush();
		$this->assertSame( $after, AFS_Cache::version(), 'Several changes in one request write once' );
	}

	public function test_site_can_force_invalidation_with_the_version_filter() {
		$first = $this->get( array( 'posts_per_page' => 5 ) )->get_headers()['ETag'];
		add_filter( 'afs_cache_version', static function ( $v ) { return $v . '-x'; } );
		$this->assertNotSame( $first, $this->get( array( 'posts_per_page' => 5 ) )->get_headers()['ETag'] );
		remove_all_filters( 'afs_cache_version' );
	}
}
