<?php
/**
 * Password-protected posts never expose an image or an excerpt through the plugin, even to a visitor who has entered the
 * password, and nothing that depends on that visitor's cookie can enter a shared cache (audit finding 1).
 *
 * The protection is read from the post itself (it has a password), not from post_password_required(), which changes with
 * the visitor's cookie.
 */
class ProtectedContentCacheTest extends AFS_Test_Case {

	const PASSWORD = 'hunter2'; // The fixture post 2005 ("UNPUB-password"); its body holds the marker BODYMARK-password.

	public function set_up() {
		parent::set_up();
		delete_option( AFS_SETTINGS );
		wp_cache_flush();
	}

	public function tear_down() {
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		remove_all_filters( 'post_thumbnail_html' );
		parent::tear_down();
	}

	/** Behave like a browser that has entered the post password: send the cookie WordPress would have set. */
	private function enter_the_password() {
		if ( ! class_exists( 'PasswordHash' ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
		}
		$hasher                                       = new PasswordHash( 8, true );
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hasher->HashPassword( wp_unslash( self::PASSWORD ) );
		$this->assertFalse( post_password_required( get_post( 2005 ) ), 'The test cookie must really unlock the post' );
	}

	private function leave() {
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		$this->assertTrue( post_password_required( get_post( 2005 ) ) );
	}

	private function give_image() {
		$attachment = self::factory()->attachment->create_object( 'cover.jpg', 2005, array( 'post_mime_type' => 'image/jpeg' ) );
		set_post_thumbnail( 2005, $attachment );
		AFS_Cache::flush();
		add_filter(
			'post_thumbnail_html',
			static function () {
				return '<img src="https://example.test/cover.jpg" alt="" data-mark="COVERIMG">';
			}
		);
	}

	private function list_for( $design ) {
		return array( 'design' => $design, 'show_image' => '1', 'show_excerpt' => '1', 'afs_year' => 2026, 'afs_month' => 10, 'posts_per_page' => 20 );
	}

	/** @return array<string,array<int,string>> */
	public function designs() {
		return array(
			'classic'   => array( 'classic' ),
			'modern'    => array( 'modern' ),
			'editorial' => array( 'editorial' ),
			'library'   => array( 'library' ),
		);
	}

	/**
	 * @dataProvider designs
	 */
	public function test_a_visitor_with_a_valid_password_cookie_still_gets_no_excerpt_or_image( $design ) {
		$this->give_image();
		$this->enter_the_password();
		$html = $this->rest( $this->list_for( $design ) )->get_data()['html'];
		$this->assertStringContainsString( 'UNPUB-password', $html, 'The title stays listed, as in any WordPress archive' );
		$this->assertStringNotContainsString( 'BODYMARK-password', $html, $design . ' leaked the protected body' );
		$this->assertStringNotContainsString( 'secret words', $html );
		$this->assertStringNotContainsString( 'Post excerpt', $html, $design . ' leaked the protected excerpt' );
		$this->assertStringNotContainsString( 'COVERIMG', $html, $design . ' leaked the protected image' );
	}

	/**
	 * @dataProvider designs
	 */
	public function test_the_response_and_its_validator_do_not_depend_on_the_cookie( $design ) {
		$this->give_image();
		$params = $this->list_for( $design );

		$without = $this->rest( $params );
		$this->enter_the_password();
		$with = $this->rest( $params );
		$this->leave();

		$this->assertSame( $without->get_data()['html'], $with->get_data()['html'], 'The cookie must not change what is rendered' );
		$this->assertSame( $without->get_headers()['ETag'], $with->get_headers()['ETag'] );
	}

	/**
	 * @dataProvider designs
	 */
	public function test_nothing_unlocked_by_a_cookie_is_served_to_the_next_visitor_from_the_cache( $design ) {
		$this->give_image();
		$params = $this->list_for( $design );

		$this->enter_the_password();
		$first = $this->rest( $params );
		$this->assertStringContainsString( 'public', $first->get_headers()['Cache-Control'], 'It is a shared-cacheable response, which is why it must not vary by cookie' );
		$this->leave();

		$second = $this->rest( $params )->get_data()['html'];
		$this->assertStringNotContainsString( 'BODYMARK-password', $second );
		$this->assertStringNotContainsString( 'COVERIMG', $second );
	}

	public function test_the_first_page_render_does_not_depend_on_the_cookie_either() {
		$this->give_image();
		foreach ( array( 'classic', 'modern', 'editorial', 'library' ) as $design ) {
			$this->enter_the_password();
			$this->reset_widget_counters();
			$_GET = array( 'afs_year' => '2026', 'afs_month' => '10' );
			$html = do_shortcode( '[ajax_filter_search design="' . $design . '" posts_per_page="20" show_image="true"]' );
			$_GET = array();
			$this->assertStringContainsString( 'UNPUB-password', $html );
			$this->assertStringNotContainsString( 'BODYMARK-password', $html, $design . ' page render leaked the protected body' );
			$this->assertStringNotContainsString( 'COVERIMG', $html );
			$this->leave();
		}
	}

	public function test_the_1x_helper_and_the_card_helpers_decide_from_the_post_not_the_cookie() {
		$this->give_image();
		$this->enter_the_password();
		$GLOBALS['post'] = get_post( 2005 );
		setup_postdata( $GLOBALS['post'] );
		$this->assertSame( '', get_the_selected_excerpt( 200 ) );
		$this->assertSame( '', AFS_Card::excerpt( 25 ) );
		$this->assertFalse( AFS_Card::has_image( 2005 ) );
		$this->assertTrue( AFS_Card::is_password_locked( 2005 ) );
		wp_reset_postdata();

		$this->leave();
		$this->assertTrue( AFS_Card::is_password_locked( 2005 ) );
		$this->assertFalse( AFS_Card::is_password_locked( 1001 ), 'An ordinary post is not protected' );
	}

	public function test_the_rss_feed_never_carries_a_protected_body_or_a_cacheable_unlocked_copy() {
		$this->enter_the_password();
		$request = new WP_REST_Request( 'GET', '/afs/v1/feed' );
		foreach ( array( 'afs_year' => 2026, 'afs_month' => 10, 'posts_per_page' => 20 ) as $k => $v ) {
			$request->set_param( $k, $v );
		}
		$with = $this->server->dispatch( $request );
		$this->assertStringContainsString( 'UNPUB-password', (string) $with->get_data() );
		$this->assertStringNotContainsString( 'BODYMARK-password', (string) $with->get_data(), 'The unlocked feed leaked the body' );
		$this->leave();

		$request = new WP_REST_Request( 'GET', '/afs/v1/feed' );
		foreach ( array( 'afs_year' => 2026, 'afs_month' => 10, 'posts_per_page' => 20 ) as $k => $v ) {
			$request->set_param( $k, $v );
		}
		$without = $this->server->dispatch( $request );
		$this->assertStringNotContainsString( 'BODYMARK-password', (string) $without->get_data() );
		$this->assertSame( $with->get_headers()['ETag'], $without->get_headers()['ETag'] );
	}

	public function test_the_cache_version_carries_an_epoch_so_an_update_purges_older_cached_output() {
		$this->assertMatchesRegularExpression( '/^' . preg_quote( (string) AFS_Cache::EPOCH, '/' ) . '\./', AFS_Cache::version() );
		$before = AFS_Cache::version();
		update_option( AFS_Cache::OPTION, '123' );
		$this->assertNotSame( $before, AFS_Cache::version() );
		$this->assertStringEndsWith( '.123', AFS_Cache::version() );
	}
}
