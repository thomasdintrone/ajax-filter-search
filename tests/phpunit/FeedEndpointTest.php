<?php
/**
 * The RSS feed endpoint (decision 0004): same query as the results, well-formed, published only, cacheable.
 */
class FeedEndpointTest extends AFS_Test_Case {

	public function set_up() {
		parent::set_up();
		update_option( 'permalink_structure', '' );
	}

	public function tear_down() {
		remove_all_filters( 'afs_enable_feed' );
		remove_all_filters( 'afs_rest_cacheable' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function ids( array $raw_config, array $raw_state ) {
		$config = AFS_Renderer::normalize_config( array_merge( array( 'posts_per_page' => 50 ), $raw_config ) );
		$state  = AFS_Renderer::normalize_state( $raw_state, $config );
		return array( $config, $state, wp_list_pluck( AFS_Query::run( $config, $state )->posts, 'ID' ) );
	}

	private function feed_ids( $xml ) {
		$doc = new DOMDocument();
		$this->assertTrue( @$doc->loadXML( $xml ), 'The feed must be well-formed XML' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$out = array();
		foreach ( $doc->getElementsByTagName( 'item' ) as $item ) {
			parse_str( (string) wp_parse_url( $item->getElementsByTagName( 'guid' )->item( 0 )->textContent, PHP_URL_QUERY ), $q );
			$out[] = (int) $q['p'];
		}
		return $out;
	}

	private function get( array $params = array(), array $headers = array(), $server = null ) {
		$request = new WP_REST_Request( 'GET', '/afs/v1/feed' );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		foreach ( $headers as $k => $v ) {
			$request->set_header( $k, $v );
		}
		return ( $server ? $server : $this->server )->dispatch( $request );
	}

	public function state_provider() {
		return array(
			'default'         => array( array(), array() ),
			'category'        => array( array(), array( 'term' => 'news' ) ),
			'keyword'         => array( array(), array( 's' => 'report' ) ),
			'year and month'  => array( array(), array( 'year' => 2024, 'month' => 3 ) ),
			'restricted set'  => array( array( 'terms' => 'news,events' ), array() ),
			'title ordering'  => array( array( 'orderby' => 'title', 'order' => 'ASC', 'posts_per_page' => 5 ), array( 'paged' => 2 ) ),
		);
	}

	/** @dataProvider state_provider */
	public function test_feed_lists_the_same_posts_as_the_results_in_the_same_order( $raw_config, $raw_state ) {
		list( $config, $state, $expected ) = $this->ids( $raw_config, $raw_state );
		$this->assertSame( $expected, $this->feed_ids( AFS_Feed::xml( $config, $state ) ) );
	}

	public function test_route_returns_rss_with_validators() {
		$res = $this->get( array( 'posts_per_page' => 5, 'afs_term' => 'news' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertStringContainsString( 'application/rss+xml', $res->get_headers()['Content-Type'] );
		$this->assertMatchesRegularExpression( '/^"[a-f0-9]{32}"$/', $res->get_headers()['ETag'] );
		$this->assertStringContainsString( 'max-age=60', $res->get_headers()['Cache-Control'] );
		$this->assertSame( 5, count( $this->feed_ids( $res->get_data() ) ) );
		$this->assertStringContainsString( '<rss version="2.0"', $res->get_data() );
	}

	public function test_matching_validator_gets_an_empty_304_and_a_change_invalidates_it() {
		$etag = $this->get( array( 'posts_per_page' => 5 ) )->get_headers()['ETag'];
		$res  = $this->get( array( 'posts_per_page' => 5 ), array( 'If-None-Match' => $etag ) );
		$this->assertSame( 304, $res->get_status() );
		$this->assertNull( $res->get_data() );

		self::factory()->post->create( array( 'post_title' => 'Feed bust arrival', 'post_status' => 'publish', 'post_date' => '2026-10-27 00:00:00' ) );
		AFS_Cache::flush();
		$again = $this->get( array( 'posts_per_page' => 5 ), array( 'If-None-Match' => $etag ) );
		$this->assertSame( 200, $again->get_status() );
		$this->assertStringContainsString( 'Feed bust arrival', $again->get_data() );
	}

	public function test_never_lists_unpublished_content_even_for_an_admin_with_a_widening_filter() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'afs_query_args', static function ( $args ) { $args['post_status'] = 'any'; $args['perm'] = 'readable'; return $args; } );
		$xml = $this->get( array( 'posts_per_page' => 50, 'afs_s' => 'UNPUB' ) )->get_data();
		foreach ( array( 'UNPUB-draft', 'UNPUB-pending', 'UNPUB-future', 'UNPUB-private', 'BODYMARK' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $xml );
		}
	}

	public function test_site_query_filter_is_applied() {
		add_filter( 'afs_query_args', static function ( $args ) { $args['post__not_in'] = array( 1001 ); return $args; } );
		$this->assertStringNotContainsString( 'Alpha report one', $this->get( array( 'posts_per_page' => 50 ) )->get_data() );
	}

	public function test_special_characters_cannot_break_the_xml() {
		$id = self::factory()->post->create( array( 'post_title' => 'Tom & Jerry <b>"quoted"</b> O\'Brien', 'post_status' => 'publish', 'post_date' => '2026-10-20 10:00:00', 'post_content' => 'x ]]> y <script>alert(1)</script>' ) );
		$xml = $this->get( array( 'afs_s' => 'Jerry' ) )->get_data();
		$doc = new DOMDocument();
		$this->assertTrue( @$doc->loadXML( $xml ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$titles = array();
		foreach ( $doc->getElementsByTagName( 'item' ) as $item ) {
			$titles[] = $item->getElementsByTagName( 'title' )->item( 0 )->textContent;
		}
		$this->assertContains( 'Tom & Jerry “quoted” O’Brien', $titles );
		$this->assertStringNotContainsString( '<script>', $xml );
		wp_delete_post( $id, true );
	}

	public function test_visitor_input_is_validated_like_the_results_route() {
		foreach ( array( array( 'posts_per_page' => 9999 ), array( 'afs_month' => 13 ), array( 'afs_page' => 0 ) ) as $params ) {
			$this->assertSame( 400, $this->get( $params )->get_status(), wp_json_encode( $params ) );
		}
	}

	public function test_signed_in_requests_are_not_cached() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$res = $this->get( array( 'posts_per_page' => 5 ) );
		$this->assertArrayNotHasKey( 'ETag', $res->get_headers() );
		$this->assertStringContainsString( 'private', $res->get_headers()['Cache-Control'] );
	}

	public function test_a_site_can_switch_the_route_off() {
		add_filter( 'afs_enable_feed', '__return_false' );
		global $wp_rest_server;
		$server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $server );
		$this->assertSame( 404, $this->get( array(), array(), $server )->get_status() );
	}

	public function test_serve_prints_the_xml_for_this_route_only() {
		$req = new WP_REST_Request( 'GET', '/afs/v1/feed' );
		$res = new WP_REST_Response( '<rss/>' );
		ob_start();
		$served = AFS_Feed::serve( false, $res, $req );
		$this->assertSame( '<rss/>', ob_get_clean() );
		$this->assertTrue( $served );

		ob_start();
		$this->assertFalse( AFS_Feed::serve( false, new WP_REST_Response( array( 'a' => 1 ) ), new WP_REST_Request( 'GET', '/afs/v1/results' ) ) );
		$this->assertSame( '', ob_get_clean(), 'The results route is untouched' );
	}

	public function test_the_global_query_and_post_are_restored_and_a_second_feed_still_renders() {
		global $wp_query, $post;
		$before_query = $wp_query;
		$before_post  = $post;
		list( $config, $state ) = $this->ids( array(), array() );
		$first  = AFS_Feed::xml( $config, $state );
		$second = AFS_Feed::xml( $config, $state );
		$this->assertSame( $before_query, $wp_query );
		$this->assertSame( $before_post, $post );
		$this->assertNotSame( '', $first );
		$this->assertSame( $first, $second, 'A second feed in one process must not come back empty' );
	}

	public function test_helper_builds_a_url_that_returns_the_same_feed() {
		list( $config, $state, $expected ) = $this->ids( array( 'posts_per_page' => 5 ), array( 'term' => 'news', 'year' => 2025 ) );
		$url = afs_feed_url( $config, $state );
		$this->assertMatchesRegularExpression( '#afs(/|%2F)v1(/|%2F)feed#', $url, 'Plain-permalink sites use ?rest_route=' );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
		$this->assertSame( $expected, $this->feed_ids( $this->get( $params )->get_data() ) );
	}
}
