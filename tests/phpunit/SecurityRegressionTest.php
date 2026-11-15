<?php
/**
 * Permanent regression gate for the security properties of 1.0.4 and 2.0.0:
 * published-only content, input validation and caps, escaping, and no outbound requests.
 *
 * @group security
 */
class SecurityRegressionTest extends AFS_Test_Case {

	/** Titles/markers that must never reach public output. */
	private const HIDDEN = array( 'UNPUB-draft', 'UNPUB-pending', 'UNPUB-future', 'UNPUB-private', 'BODYMARK-draft', 'BODYMARK-pending', 'BODYMARK-future', 'BODYMARK-private', 'BODYMARK-password' );

	/** The part of a full widget that lists results; the search box echoes the visitor's own text by design. */
	private function results_part( $html ) {
		$pos = strpos( $html, 'class="afs-results"' );
		$this->assertNotFalse( $pos, 'Results region not found' );
		return substr( $html, $pos );
	}

	private function assertNothingHidden( $html, $where ) {
		foreach ( self::HIDDEN as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, "$needle leaked through $where" );
		}
	}

	public function test_shortcode_never_lists_unpublished_content() {
		foreach ( array( '', 'UNPUB', 'BODYMARK', 'secret' ) as $search ) {
			$this->reset_widget_counters();
			$out = do_shortcode( '[ajax_filter_search posts_per_page="50" search="' . $search . '"]' );
			$this->assertNothingHidden( $this->results_part( $out ), 'shortcode search "' . $search . '"' );
		}
	}

	public function test_deep_link_search_never_lists_unpublished_content() {
		foreach ( array( 'UNPUB', 'BODYMARK-draft', 'secret words' ) as $search ) {
			$this->reset_widget_counters();
			$_GET = array( 'afs_s' => $search );
			$out  = do_shortcode( '[ajax_filter_search posts_per_page="50"]' );
			$this->assertNothingHidden( $this->results_part( $out ), 'deep link "' . $search . '"' );
		}
	}

	public function test_rest_never_lists_unpublished_content() {
		foreach ( array( '', 'UNPUB', 'BODYMARK', 'secret' ) as $search ) {
			foreach ( array( 'post', 'page' ) as $type ) {
				$data = $this->rest( array( 'afs_s' => $search, 'posts_per_page' => 100, 'post_type' => $type ) )->get_data();
				$this->assertNothingHidden( $data['html'], "REST type=$type search=\"$search\"" );
			}
		}
	}

	public function test_password_protected_post_exposes_title_only() {
		// Visitors browsing see the title (as in any WordPress archive); WordPress core's own search hides protected posts.
		$data = $this->rest( array( 'posts_per_page' => 100 ) )->get_data();
		$this->assertStringContainsString( 'UNPUB-password', $data['html'], 'Title appears, as in any WordPress archive' );
		$searched = $this->rest( array( 'afs_s' => 'UNPUB-password', 'posts_per_page' => 20 ) )->get_data();
		$this->assertStringNotContainsString( 'BODYMARK-password', $searched['html'] );
		$this->assertStringNotContainsString( 'BODYMARK-password', $data['html'], 'Protected body must not leak' );
		$this->assertStringNotContainsString( 'secret words', $data['html'] );
	}

	/**
	 * Published-only must hold even if a site's afs_query_args filter widens the status or asks for permission-based
	 * visibility. AFS_Query resets both after the filter runs.
	 */
	public function test_published_only_survives_query_args_filter() {
		add_filter(
			'afs_query_args',
			static function ( $args ) {
				$args['post_status'] = 'any';
				$args['perm']        = 'readable';
				return $args;
			}
		);
		$data = $this->rest( array( 'afs_s' => 'UNPUB', 'posts_per_page' => 20 ) )->get_data();
		$this->assertNothingHidden( $data['html'], 'REST with widening afs_query_args filter' );
	}

	public function hostile_search_provider() {
		$cases = array(
			'script-tag'   => '"><script>alert(1)</script>',
			'img-onerror'  => '<img src=x onerror=alert(1)>',
			'sql-ish'      => "' OR 1=1--",
			'rtl-override' => "\u{202E}<b>x</b>",
			'template'     => '{{7*7}}${7*7}',
			'long'         => str_repeat( 'A', 5000 ),
		);
		foreach ( $cases as $name => $value ) {
			yield $name => array( $value );
		}
	}

	/** @dataProvider hostile_search_provider */
	public function test_search_text_is_escaped_in_rest_and_page( $value ) {
		$data = $this->rest( array( 'afs_s' => $value ) );
		$this->assertContains( $data->get_status(), array( 200, 400 ) );
		if ( 200 === $data->get_status() ) {
			$html = $data->get_data()['html'];
			$this->assertStringNotContainsString( '<script>alert', $html );
			$this->assertStringNotContainsString( '<img src=x', $html );
		}
		$_GET = array( 'afs_s' => $value );
		$page = do_shortcode( '[ajax_filter_search]' );
		$this->assertStringNotContainsString( '<script>alert', $page );
		$this->assertStringNotContainsString( '<img src=x', $page );
	}

	/**
	 * Finding (low, hygiene): a raw NUL byte in afs_s is echoed back inside the search box value. Browsers replace it
	 * with U+FFFD, so it is not exploitable, but control characters should be stripped. Fix planned with the state
	 * normalizer work (2.0.1 candidate); run with the known-gap group.
	 *
	 * @group known-gap
	 */
	public function test_control_characters_are_not_reflected() {
		$_GET = array( 'afs_s' => "abc\0def\x07" );
		$this->assertStringNotContainsString( "\0", do_shortcode( '[ajax_filter_search]' ) );
	}

	public function rest_bounds_provider() {
		$cases = array(
			'per-page-101'      => array( 'posts_per_page' => 101 ),
			'per-page-0'        => array( 'posts_per_page' => 0 ),
			'page-0'            => array( 'afs_page' => 0 ),
			'page-huge'         => array( 'afs_page' => 100001 ),
			'month-13'          => array( 'afs_month' => 13 ),
			'month-negative'    => array( 'afs_month' => -1 ),
			'year-10000'        => array( 'afs_year' => 10000 ),
			'instance-0'        => array( 'instance' => 0 ),
			'instance-51'       => array( 'instance' => 51 ),
			'per-page-text'     => array( 'posts_per_page' => 'ten' ),
		);
		foreach ( $cases as $name => $params ) {
			yield $name => array( $params );
		}
	}

	/** @dataProvider rest_bounds_provider */
	public function test_rest_rejects_out_of_range_values( $params ) {
		$this->assertSame( 400, $this->rest( $params )->get_status() );
	}

	public function test_rest_array_typed_input_does_not_crash() {
		$response = $this->rest( array( 'afs_s' => array( 'x' ), 'afs_term' => array( 'a', 'b' ), 'terms' => array( 'a' ) ) );
		$this->assertContains( $response->get_status(), array( 200, 400 ) );
	}

	public function test_rest_cannot_choose_hidden_post_types_or_off_site_links() {
		$default = $this->rest( array() )->get_data();
		foreach ( array( 'revision', 'nav_menu_item', 'attachment', 'wp_template', 'oembed_cache', 'does_not_exist' ) as $type ) {
			$data = $this->rest( array( 'post_type' => $type ) )->get_data();
			$this->assertSame( $default['found'], $data['found'], "post_type=$type must fall back to the configured type" );
		}
		foreach ( array( 'http://evil.example/x', 'javascript:alert(1)', 'data:text/html,x', '//evil.example' ) as $url ) {
			$html = $this->rest( array( 'posts_per_page' => 2, 'base_url' => $url ) )->get_data()['html'];
			$this->assertStringNotContainsString( 'evil.example', $html );
			$this->assertStringNotContainsString( 'javascript:', $html );
			$this->assertStringNotContainsString( 'data:text', $html );
		}
	}

	public function test_no_outbound_http_requests_while_rendering() {
		$calls = array();
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$calls ) {
				$calls[] = $url;
				return new WP_Error( 'afs_test_blocked', 'blocked' );
			},
			10,
			3
		);
		do_shortcode( '[ajax_filter_search][afs_feed]' );
		$this->rest( array( 'afs_s' => 'report' ) );
		$this->rest( array( 'afs_term' => 'news', 'afs_year' => 2025 ) );
		render_block( parse_blocks( '<!-- wp:ajax-filter-search/feed /-->' )[0] );
		$this->assertSame( array(), $calls, 'The plugin must not make outbound HTTP requests' );
	}

	public function test_public_read_route_registered_without_write_methods() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/afs/v1/results', $routes );
		foreach ( $routes['/afs/v1/results'] as $handler ) {
			$this->assertSame( array( 'GET' => true ), $handler['methods'], 'The route must be read-only' );
		}
		$mine = array_values( array_filter( array_keys( $routes ), static function ( $r ) {
			return 0 === strpos( $r, '/afs/' ) && '/afs/v1' !== $r;
		} ) );
		$this->assertSame( array( '/afs/v1/results' ), $mine, 'Unexpected additional /afs routes' );
	}
}
