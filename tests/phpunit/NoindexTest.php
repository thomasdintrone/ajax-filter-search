<?php
/**
 * G1: filtered views of a feed page are noindex, follow; the plain page and unrelated pages are untouched.
 */
class NoindexTest extends AFS_Test_Case {

	public function tear_down() {
		remove_all_filters( 'afs_noindex_filtered_states' );
		parent::tear_down();
	}

	private function robots_for( $content, array $query ) {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content ) );
		$_GET = $query;
		$this->go_to( get_permalink( $id ) );
		$_GET = $query; // go_to() resets the globals.
		return apply_filters( 'wp_robots', array() );
	}

	public function test_a_filtered_feed_page_is_noindex_follow() {
		foreach ( array( 'afs_s', 'afs_term', 'afs_month', 'afs_year', 'afs_page', 'afs_layout' ) as $key ) {
			$robots = $this->robots_for( '[ajax_filter_search]', array( $key => '2' ) );
			$this->assertTrue( ! empty( $robots['noindex'] ), $key );
			$this->assertTrue( ! empty( $robots['follow'] ), $key );
		}
	}

	public function test_the_plain_page_stays_indexable() {
		$this->assertArrayNotHasKey( 'noindex', $this->robots_for( '[ajax_filter_search]', array() ) );
	}

	public function test_block_and_legacy_shortcode_pages_are_covered() {
		$this->assertNotEmpty( $this->robots_for( '<!-- wp:ajax-filter-search/feed /-->', array( 'afs_term' => 'news' ) )['noindex'] );
		$this->assertNotEmpty( $this->robots_for( '[afs_feed]', array( 'afs_term' => 'news' ) )['noindex'] );
	}

	public function test_a_page_without_a_feed_is_never_touched() {
		$this->assertArrayNotHasKey( 'noindex', $this->robots_for( 'Just text', array( 'afs_term' => 'news' ) ) );
	}

	public function test_the_setting_and_the_filter_can_turn_it_off() {
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_general_noindex_filtered' => 0 ) ) );
		$this->assertArrayNotHasKey( 'noindex', $this->robots_for( '[ajax_filter_search]', array( 'afs_term' => 'news' ) ) );
		delete_option( AFS_SETTINGS );

		add_filter( 'afs_noindex_filtered_states', '__return_false' );
		$this->assertArrayNotHasKey( 'noindex', $this->robots_for( '[ajax_filter_search]', array( 'afs_term' => 'news' ) ) );
	}

	public function test_default_is_on_for_a_site_that_saved_settings_before_the_option_existed() {
		update_option( AFS_SETTINGS, array( 'afs_general_posts_per_page' => 7 ) ); // A 2.0.0 option array.
		$this->assertSame( 1, (int) AFSAdmin::afs_get_option( 'afs_general_noindex_filtered' ) );
	}

	public function test_the_setting_is_sanitized_to_zero_or_one() {
		$this->assertSame( 0, AFSAdmin::sanitize( array( 'afs_general_noindex_filtered' => '0' ) )['afs_general_noindex_filtered'] );
		$this->assertSame( 1, AFSAdmin::sanitize( array( 'afs_general_noindex_filtered' => '1' ) )['afs_general_noindex_filtered'] );
		$this->assertSame( 0, AFSAdmin::sanitize( array() )['afs_general_noindex_filtered'], 'An unchecked form field is off, as for the other yes/no settings' );
	}
}
