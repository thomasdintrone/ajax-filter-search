<?php
/**
 * The 2.3.0 refinements of the two new designs: search placeholder, icon layout switch, source order, the filter state
 * classes behind the Reset rule, and the empty state. Classic and Modern must show none of it.
 */
class RefinementsTest extends AFS_Test_Case {

	public function set_up() {
		parent::set_up();
		delete_option( AFS_Design::OPTION );
		delete_option( AFS_SETTINGS );
	}

	private function results( array $params ) {
		return $this->rest( $params )->get_data();
	}

	private function feed( $shortcode ) {
		$this->reset_widget_counters();
		return do_shortcode( $shortcode );
	}

	public function test_the_new_designs_get_a_placeholder_from_the_post_types_own_label() {
		foreach ( array( 'editorial', 'library' ) as $design ) {
			$html = $this->feed( '[ajax_filter_search design="' . $design . '"]' );
			$this->assertStringContainsString( 'placeholder="Search Posts"', $html, $design );
			$this->assertStringContainsString( '<label for="afs-wrapper-s">Search</label>', $html, 'The label stays for assistive technology' );
		}
		$html = $this->feed( '[ajax_filter_search design="editorial" post_type="page"]' );
		$this->assertStringContainsString( 'placeholder="Search Pages"', $html );
	}

	public function test_classic_and_modern_have_no_placeholder_and_keep_their_text_switch() {
		foreach ( array( 'classic', 'modern' ) as $design ) {
			$html = $this->feed( '[ajax_filter_search design="' . $design . '"]' );
			$this->assertStringNotContainsString( 'placeholder=', $html, $design );
			$this->assertStringNotContainsString( 'afs-icon', $html, $design );
			$this->assertMatchesRegularExpression( '/data-afs-layout="list"[^>]*>List View<\/button>/', $html );
			$this->assertStringNotContainsString( 'afs-is-filtered', $html );
			$this->assertStringNotContainsString( 'afs-has-chips', $html );
		}
	}

	public function test_the_new_designs_use_icon_buttons_with_the_same_accessible_names() {
		$html = $this->feed( '[ajax_filter_search design="editorial"]' );
		$this->assertSame( 2, substr_count( $html, 'class="afs-icon"' ) );
		$this->assertMatchesRegularExpression( '/<button type="button" class="afs-btn afs-switch-btn" data-afs-layout="list" aria-pressed="false"><svg[^>]*aria-hidden="true"[^>]*>.*?<\/svg><span class="afs-sr-only">List View<\/span><\/button>/s', $html );
		$this->assertMatchesRegularExpression( '/data-afs-layout="grid" aria-pressed="true">.*?<span class="afs-sr-only">Grid View<\/span>/s', $html, 'The Editorial Grid starts as a grid' );
	}

	public function test_the_layout_switch_follows_the_tabs_in_the_new_designs_and_the_filters_in_the_old_ones() {
		$new = $this->feed( '[ajax_filter_search design="editorial"]' );
		$this->assertLessThan( strpos( $new, 'class="afs-Filters"' ), strpos( $new, 'class="afs-Switch"' ), 'Source order matches the visual order' );
		$this->assertGreaterThan( strpos( $new, 'class="afs-Tabs"' ), strpos( $new, 'class="afs-Switch"' ) );
		$old = $this->feed( '[ajax_filter_search design="modern"]' );
		$this->assertGreaterThan( strpos( $old, 'class="afs-Filters"' ), strpos( $old, 'class="afs-Switch"' ) );
	}

	public function test_the_root_says_when_filters_are_active_and_when_chips_are_on() {
		$idle = $this->feed( '[ajax_filter_search design="editorial"]' );
		$this->assertStringNotContainsString( 'afs-is-filtered', $idle );
		$this->assertStringNotContainsString( 'afs-has-chips', $idle );

		$_GET['afs_s'] = 'alpha';
		$active = $this->feed( '[ajax_filter_search design="editorial" show_chips="true"]' );
		$this->assertMatchesRegularExpression( '/class="afs afs-wrapper afs-layout-grid afs-design-editorial afs-discovery afs-has-chips afs-is-filtered"/', $active );

		$_GET = array( 'afs_term' => 'news' );
		$this->assertStringContainsString( 'afs-is-filtered', $this->feed( '[ajax_filter_search design="library"]' ) );

		$_GET = array( 'afs_sort' => 'oldest' );
		$this->assertStringNotContainsString( 'afs-is-filtered', $this->feed( '[ajax_filter_search design="library" show_sort="true"]' ), 'A sort is not a filter' );
	}

	public function test_an_empty_search_says_what_was_searched_and_offers_one_way_out() {
		$html = $this->results( array( 'design' => 'editorial', 'show_chips' => '1', 'afs_s' => 'zebra <b>submarine</b>' ) )['html'];
		$this->assertStringContainsString( 'No results for “zebra submarine”.', $html, 'Tags are stripped from the query' );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( 'Try a different word or clear the filters.', $html );
		$this->assertSame( 1, substr_count( $html, 'data-afs-clear="all"' ), 'One Clear all, not a chip link and a button' );
		$this->assertStringContainsString( 'afs-recover', $html );
		$this->assertStringNotContainsString( 'afs-chip-clear', $html );
		$this->assertStringContainsString( 'afs-chip"', $html, 'The chip itself stays, so the search can be removed' );
	}

	public function test_an_empty_filter_without_a_search_has_its_own_wording() {
		$html = $this->results( array( 'design' => 'library', 'afs_term' => 'news', 'afs_year' => 2099 ) )['html'];
		$this->assertStringContainsString( 'No results match these filters.', $html );
	}

	public function test_classic_and_modern_keep_the_original_empty_state_and_chips() {
		foreach ( array( 'classic', 'modern' ) as $design ) {
			$html = $this->results( array( 'design' => $design, 'show_chips' => '1', 'afs_s' => 'zebra submarine' ) )['html'];
			$this->assertStringContainsString( '<p class="afs-none">No results found.</p>', $html, $design );
			$this->assertStringContainsString( 'afs-chip-clear', $html, 'Their chips keep their own Clear all' );
			$this->assertStringContainsString( 'afs-recover', $html );
			$this->assertStringNotContainsString( 'afs-none__title', $html );
		}
	}

	public function test_the_missing_image_tile_is_a_tile_with_a_monogram_badge() {
		$html = $this->results( array( 'design' => 'editorial', 'show_image' => '1', 'afs_year' => 2024, 'afs_month' => 1 ) )['html'];
		$this->assertMatchesRegularExpression( '/<span class="afs-img afs-img--none" aria-hidden="true"><span class="afs-img__mono">[A-Z]<\/span><\/span>/', $html );
	}

	// --- A feed's own chips choice survives an AJAX update (audit finding 3) ---

	private function client_config_of( $html ) {
		$this->assertSame( 1, preg_match( '/data-afs-config="([^"]*)"/', $html, $m ) );
		return json_decode( html_entity_decode( $m[1] ), true );
	}

	/**
	 * @return array<string,array<int,string>>
	 */
	public function every_design() {
		return array( 'classic' => array( 'classic' ), 'modern' => array( 'modern' ), 'editorial' => array( 'editorial' ), 'library' => array( 'library' ) );
	}

	/**
	 * @dataProvider every_design
	 */
	public function test_a_feed_with_chips_off_keeps_them_off_through_ajax_when_the_site_setting_turns_them_on( $design ) {
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_general_show_chips' => 1 ) ) );

		$html   = $this->feed( '[ajax_filter_search design="' . $design . '" show_chips="false"]' );
		$config = $this->client_config_of( $html );
		$this->assertSame( '0', $config['show_chips'], 'The effective choice, off, must be sent' );
		$this->assertStringNotContainsString( 'afs-has-chips', $html );

		$_GET = array( 'afs_term' => 'news' );
		$page = $this->feed( '[ajax_filter_search design="' . $design . '" show_chips="false"]' );
		$_GET = array();
		$this->assertStringNotContainsString( 'class="afs-chips"', $page, 'First load shows no chips' );

		$ajax = $this->results( array_merge( $config, array( 'afs_term' => 'news', 'posts_per_page' => 3 ) ) )['html'];
		$this->assertStringNotContainsString( 'afs-chips', $ajax, 'The update must not switch the chips on' );
		$this->assertStringNotContainsString( 'afs-chip-clear', $ajax );
		$this->assertStringNotContainsString( 'data-afs-clear', $ajax );
	}

	/**
	 * @dataProvider every_design
	 */
	public function test_a_feed_with_chips_on_keeps_them_on_when_the_site_setting_is_off( $design ) {
		$html   = $this->feed( '[ajax_filter_search design="' . $design . '" show_chips="true"]' );
		$config = $this->client_config_of( $html );
		$this->assertSame( '1', $config['show_chips'] );
		$ajax = $this->results( array_merge( $config, array( 'afs_term' => 'news', 'posts_per_page' => 3 ) ) )['html'];
		$this->assertStringContainsString( 'afs-chips', $ajax );
	}

	public function test_the_block_override_is_kept_too() {
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_general_show_chips' => 1 ) ) );
		$this->reset_widget_counters();
		$html = render_block( array( 'blockName' => 'ajax-filter-search/feed', 'attrs' => array( 'design' => 'editorial', 'showChips' => 'hide' ) ) );
		$this->assertSame( '0', $this->client_config_of( $html )['show_chips'] );
		$this->assertStringNotContainsString( 'afs-has-chips', $html );
	}

	public function test_nothing_extra_is_sent_when_the_feed_and_the_site_agree() {
		// Site setting off (the default) and a feed that does not mention chips: the client config is exactly what it was.
		foreach ( array( 'classic', 'modern', 'editorial', 'library' ) as $design ) {
			$this->assertArrayNotHasKey( 'show_chips', $this->client_config_of( $this->feed( '[ajax_filter_search design="' . $design . '"]' ) ), $design );
		}
		// Site setting on and a feed that does not mention chips: on, sent as before.
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_general_show_chips' => 1 ) ) );
		$this->assertSame( '1', $this->client_config_of( $this->feed( '[ajax_filter_search design="classic"]' ) )['show_chips'] );
	}

	public function test_reset_is_shown_in_the_stylesheet_only_for_a_feed_without_chips() {
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_general_show_chips' => 1 ) ) );
		$_GET = array( 'afs_term' => 'news' );
		$off  = $this->feed( '[ajax_filter_search design="editorial" show_chips="false"]' );
		$on   = $this->feed( '[ajax_filter_search design="editorial"]' );
		$_GET = array();
		$this->assertMatchesRegularExpression( '/class="afs afs-wrapper [^"]*afs-is-filtered"/', $off );
		$this->assertDoesNotMatchRegularExpression( '/class="afs afs-wrapper [^"]*afs-has-chips/', $off, 'No chips, so Reset is the way out' );
		$this->assertMatchesRegularExpression( '/class="afs afs-wrapper [^"]*afs-has-chips afs-is-filtered"/', $on, 'Chips on, so Reset stays hidden' );
	}
}
