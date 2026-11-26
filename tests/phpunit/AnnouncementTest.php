<?php
/**
 * The "What's new" card on the settings screen (2.3.0): who sees it, when, how it is dismissed, and that it never touches
 * the front end or makes an outside request.
 */
class AnnouncementTest extends AFS_Test_Case {

	private $admin;

	public function set_up() {
		parent::set_up();
		delete_option( AFS_SETTINGS );
		delete_option( AFS_Design::OPTION );
		delete_option( AFS_Announcement::OPTION );
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
	}

	public function tear_down() {
		$_GET  = array();
		$_POST = array();
		delete_option( AFS_Announcement::OPTION );
		remove_all_filters( 'wp_die_handler' );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function screen() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		ob_start();
		AFSAdmin::options_page();
		return ob_get_clean();
	}

	public function test_an_existing_site_sees_the_card_on_the_settings_screen() {
		$this->assertTrue( AFS_Announcement::should_show() );
		$html = $this->screen();
		$this->assertStringContainsString( 'id="afs-news"', $html );
		$this->assertStringContainsString( 'Meet the next generation of Ajax Filter Search.', $html );
		$this->assertStringContainsString( 'Your existing feeds are unchanged.', $html );
		$this->assertStringContainsString( 'Explore the new designs', $html );
		$this->assertStringContainsString( 'Maybe later', $html );
		$this->assertStringNotContainsString( 'class="afs-admin__whatsnew"', $html, 'The reopen link is for when the card is hidden' );
	}

	public function test_only_people_who_can_manage_the_plugin_see_it() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$this->assertFalse( AFS_Announcement::should_show() );
		wp_set_current_user( 0 );
		$this->assertFalse( AFS_Announcement::should_show() );
		$this->assertFalse( AFS_Announcement::should_show( $editor ) );
		$this->assertTrue( AFS_Announcement::should_show( $this->admin ) );
	}

	public function test_a_fresh_install_never_sees_it() {
		AFS_Design::activate();
		$this->assertSame( AFS_Announcement::VERSION, get_option( AFS_Announcement::OPTION ) );
		$this->assertFalse( AFS_Announcement::should_show() );
	}

	public function test_activating_on_a_site_that_already_has_settings_does_not_hide_it() {
		update_option( AFS_SETTINGS, array( 'afs_general_posts_per_page' => 7 ) );
		AFS_Design::activate();
		$this->assertFalse( get_option( AFS_Announcement::OPTION ) );
		$this->assertTrue( AFS_Announcement::should_show() );
	}

	public function test_a_site_that_already_uses_a_new_design_does_not_need_it() {
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_appearance_design' => 'editorial' ) ) );
		$this->assertFalse( AFS_Announcement::should_show() );
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_appearance_design' => 'modern' ) ) );
		$this->assertTrue( AFS_Announcement::should_show() );
	}

	public function test_dismissing_is_remembered_for_that_user_only() {
		$other = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertTrue( AFS_Announcement::dismiss( $this->admin ) );
		$this->assertSame( AFS_Announcement::VERSION, get_user_meta( $this->admin, AFS_Announcement::USER_META, true ) );
		$this->assertFalse( AFS_Announcement::should_show() );
		$this->assertTrue( AFS_Announcement::should_show( $other ), 'Another administrator has not seen it yet' );
		$this->assertStringNotContainsString( 'id="afs-news"', $this->screen() );
		$this->assertStringContainsString( 'class="afs-admin__whatsnew"', $this->screen(), 'The header offers it again' );
	}

	public function test_someone_without_the_capability_cannot_record_a_dismissal() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->assertFalse( AFS_Announcement::dismiss( $editor ) );
		$this->assertSame( '', get_user_meta( $editor, AFS_Announcement::USER_META, true ) );
	}

	public function test_the_reopen_link_shows_it_again_without_changing_anything() {
		AFS_Announcement::dismiss( $this->admin );
		$_GET['afs_whatsnew'] = '1';
		$this->assertTrue( AFS_Announcement::should_show() );
		$this->assertStringContainsString( 'id="afs-news"', $this->screen() );
		$this->assertSame( AFS_Announcement::VERSION, get_user_meta( $this->admin, AFS_Announcement::USER_META, true ), 'Showing it again does not clear the dismissal' );
		$this->assertStringContainsString( 'afs_whatsnew=1', AFS_Announcement::reopen_url() );
	}

	public function test_the_dismiss_handler_needs_a_valid_nonce() {
		add_filter(
			'wp_die_handler',
			static function () {
				return static function () {
					throw new RuntimeException( 'died' );
				};
			}
		);
		$_REQUEST['_wpnonce'] = 'not-a-nonce';
		$_POST['_wpnonce']    = 'not-a-nonce';
		try {
			AFS_Announcement::handle_dismiss();
			$this->fail( 'A bad nonce must stop the request' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'died', $e->getMessage() );
		}
		$this->assertSame( '', get_user_meta( $this->admin, AFS_Announcement::USER_META, true ) );
	}

	public function test_the_card_has_what_it_needs_to_be_accessible_and_no_outside_requests() {
		$html = $this->screen();
		$this->assertMatchesRegularExpression( '/<section id="afs-news" class="afs-news" role="region" aria-labelledby="afs-news-title">/', $html );
		$this->assertStringContainsString( 'aria-label="Dismiss this announcement"', $html );
		$this->assertStringContainsString( 'id="afs-news-title"', $html );
		$this->assertStringContainsString( 'role="status"', $html );
		$this->assertStringNotContainsString( 'role="alert"', $html );
		$this->assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $html );
		$this->assertStringContainsString( '(opens in a new tab)', $html );
		$this->assertMatchesRegularExpression( '/<div class="afs-news__shots" aria-hidden="true">/', $html, 'The screenshots are decorative' );
		preg_match_all( '/(?:src|href)="(https?:[^"]+)"/', substr( $html, strpos( $html, 'id="afs-news"' ), strpos( $html, 'id="afs-form"' ) - strpos( $html, 'id="afs-news"' ) ), $m );
		$external = array_values( array_filter( $m[1], static function ( $u ) { return 0 !== strpos( $u, home_url() ) && 0 !== strpos( $u, site_url() ) && 0 !== strpos( $u, 'http://example.org' ); } ) );
		$this->assertSame( array( AFS_Announcement::CHANGELOG ), $external, 'The changelog link is the only address that leaves the site, and nothing is loaded from it' );
	}

	public function test_the_shipped_images_exist_and_are_small() {
		foreach ( array( 'whatsnew-editorial.webp', 'whatsnew-library.webp' ) as $file ) {
			$path = AFS_PLUGIN_PATH . 'admin/images/' . $file;
			$this->assertFileExists( $path );
			$this->assertLessThan( 120 * 1024, filesize( $path ), $file . ' should stay small' );
		}
	}

	public function test_it_does_not_change_the_front_end() {
		$this->reset_widget_counters();
		$before = $this->normalize( do_shortcode( '[ajax_filter_search design="classic"]' ) );
		AFS_Announcement::dismiss( $this->admin );
		$this->reset_widget_counters();
		$this->assertSame( $before, $this->normalize( do_shortcode( '[ajax_filter_search design="classic"]' ) ) );
	}

	public function test_nothing_is_requested_from_outside_while_the_card_renders() {
		$requests = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;
				return new WP_Error( 'blocked', 'blocked' );
			}
		);
		$this->screen();
		$this->assertSame( 0, $requests );
	}
}
