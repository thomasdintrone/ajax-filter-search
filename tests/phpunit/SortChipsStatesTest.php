<?php
/**
 * B9 sort, B1 chips and Clear all, A8 empty-state recovery. All opt-in: with the flags off the output is the 2.0.0 output
 * (the frozen snapshots prove that); these tests cover what changes when they are on.
 */
class SortChipsStatesTest extends AFS_Test_Case {

	private function config( array $raw = array() ) {
		return AFS_Renderer::normalize_config( array_merge( array( 'posts_per_page' => 50 ), $raw ) );
	}

	private function titles( array $raw_config, array $raw_state ) {
		$config = $this->config( $raw_config );
		$state  = AFS_Renderer::normalize_state( $raw_state, $config );
		return wp_list_pluck( AFS_Query::run( $config, $state )->posts, 'post_title' );
	}

	public function sort_provider() {
		return array(
			'newest'   => array( 'newest' ),
			'oldest'   => array( 'oldest' ),
			'title'    => array( 'title' ),
			'modified' => array( 'modified' ),
			'comments' => array( 'comments' ),
		);
	}

	/** @dataProvider sort_provider */
	public function test_each_sort_orders_the_results( $sort ) {
		$titles = $this->titles( array(), array( 'sort' => $sort ) );
		$this->assertNotEmpty( $titles );
		if ( 'newest' === $sort ) {
			$this->assertSame( 'Alpha report one', end( $titles ), 'Oldest post last' );
		}
		if ( 'oldest' === $sort ) {
			$this->assertSame( 'Alpha report one', $titles[0] );
		}
		if ( 'title' === $sort ) {
			$sorted = $titles;
			sort( $sorted );
			$this->assertSame( $sorted, $titles );
		}
	}

	public function test_unknown_sort_is_ignored_and_the_feeds_own_order_applies() {
		$config = $this->config( array( 'orderby' => 'title', 'order' => 'ASC' ) );
		$state  = AFS_Renderer::normalize_state( array( 'sort' => 'DROP TABLE' ), $config );
		$this->assertSame( '', $state['sort'] );
		$titles = wp_list_pluck( AFS_Query::run( $config, $state )->posts, 'post_title' );
		$sorted = $titles;
		sort( $sorted );
		$this->assertSame( $sorted, $titles );
	}

	public function test_sort_works_through_the_rest_route_and_the_url_keys() {
		$res = $this->rest( array( 'afs_sort' => 'oldest', 'posts_per_page' => 3 ) )->get_data();
		$this->assertSame( 'oldest', $res['meta']['state']['sort'] );
		$this->assertStringContainsString( 'Alpha report one', $res['html'] );
		$this->assertContains( 'afs_sort', AFS_Renderer::URL_KEYS );
		$_GET['afs_sort'] = 'oldest';
		$this->assertSame( 'oldest', AFS_Renderer::request_state()['sort'] );
		unset( $_GET['afs_sort'] );
	}

	public function test_sort_control_is_absent_by_default_and_present_when_enabled() {
		$off = do_shortcode( '[ajax_filter_search]' );
		$this->assertStringNotContainsString( 'afs_sort', $off );
		$this->reset_widget_counters();
		$on = do_shortcode( '[ajax_filter_search show_sort="true"]' );
		$this->assertStringContainsString( 'name="afs_sort"', $on );
		foreach ( array( 'Newest', 'Oldest', 'A to Z', 'Recently updated', 'Most commented' ) as $label ) {
			$this->assertStringContainsString( '>' . $label . '<', $on );
		}
		$this->assertMatchesRegularExpression( '/<option value="newest" selected/', $on, 'The default (date, newest first) is preselected' );
		$this->assertStringContainsString( 'data-default="newest"', $on );
	}

	public function test_the_selected_sort_follows_the_url_and_an_unmappable_default_gets_its_own_option() {
		$_GET['afs_sort'] = 'title';
		$this->assertMatchesRegularExpression( '/<option value="title" selected/', do_shortcode( '[ajax_filter_search show_sort="true"]' ) );
		unset( $_GET['afs_sort'] );

		$this->reset_widget_counters();
		$html = do_shortcode( '[ajax_filter_search show_sort="true" orderby="title" order="DESC"]' );
		$this->assertStringContainsString( '<option value="" selected', $html );
		$this->assertStringContainsString( 'Default order', $html );
	}

	public function test_the_setting_turns_the_helpers_on_and_the_shortcode_overrides_it() {
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_general_show_sort' => 1, 'afs_general_show_chips' => 1 ) ) );
		$this->assertTrue( $this->config()['show_sort'] );
		$this->assertTrue( $this->config()['show_chips'] );
		$this->assertFalse( $this->config( array( 'show_sort' => 'false' ) )['show_sort'] );
	}

	public function test_chips_show_each_active_filter_with_a_link_that_removes_only_it() {
		$config = $this->config( array( 'show_chips' => 'true', 'posts_per_page' => 5 ) );
		$state  = AFS_Renderer::normalize_state( array( 's' => 'report', 'term' => 'news', 'month' => 3, 'year' => 2024, 'sort' => 'oldest', 'paged' => 1 ), $config );
		$html   = AFS_Renderer::render_results( $config, $state, 'http://example.org/page/' )['html'];

		$this->assertStringContainsString( 'role="group" aria-label="Active filters"', $html );
		foreach ( array( 'Search: report', 'News', 'March', '2024' ) as $label ) {
			$this->assertStringContainsString( $label, $html );
		}
		// The term chip's link keeps the other filters and the sort, and drops only the category.
		preg_match( '/<a class="afs-chip" href="([^"]+)" data-afs-clear="term"/', $html, $m );
		$url = html_entity_decode( $m[1] );
		$this->assertStringNotContainsString( 'afs_term', $url );
		foreach ( array( 'afs_s=report', 'afs_month=3', 'afs_year=2024', 'afs_sort=oldest' ) as $kept ) {
			$this->assertStringContainsString( $kept, $url );
		}
		// Clear all drops every filter but keeps the sort.
		preg_match( '/<a class="afs-chip-clear" href="([^"]+)" data-afs-clear="all">Clear all</', $html, $m );
		$clear = html_entity_decode( $m[1] );
		$this->assertStringContainsString( 'afs_sort=oldest', $clear );
		foreach ( array( 'afs_s', 'afs_term', 'afs_month', 'afs_year' ) as $gone ) {
			$this->assertDoesNotMatchRegularExpression( '/[?&]' . $gone . '=/', $clear, $gone );
		}
	}

	public function test_chips_do_not_appear_without_filters_or_when_off() {
		$config = $this->config( array( 'show_chips' => 'true' ) );
		$this->assertStringNotContainsString( 'afs-chips', AFS_Renderer::render_results( $config, AFS_Renderer::normalize_state( array(), $config ), 'http://example.org/' )['html'] );

		$off   = $this->config();
		$state = AFS_Renderer::normalize_state( array( 'term' => 'news' ), $off );
		$this->assertStringNotContainsString( 'afs-chips', AFS_Renderer::render_results( $off, $state, 'http://example.org/' )['html'] );
	}

	public function test_chip_text_and_links_are_escaped() {
		$config = $this->config( array( 'show_chips' => 'true' ) );
		$state  = AFS_Renderer::normalize_state( array( 's' => '"><img src=x onerror=alert(1)>' ), $config );
		$html   = AFS_Renderer::render_results( $config, $state, 'http://example.org/' )['html'];
		$this->assertStringNotContainsString( '<img', $html, 'The text must not become markup' );
		$this->assertStringContainsString( 'afs-chip', $html );
	}

	public function test_an_empty_result_offers_a_way_out_only_when_enabled_and_filters_are_active() {
		$on    = $this->config( array( 'show_chips' => 'true' ) );
		$state = AFS_Renderer::normalize_state( array( 's' => 'zzzznomatch' ), $on );
		$html  = AFS_Renderer::render_results( $on, $state, 'http://example.org/' )['html'];
		$this->assertStringContainsString( 'No results found.', $html );
		$this->assertStringContainsString( 'class="afs-recover"', $html );
		$this->assertStringContainsString( 'Clear all filters', $html );

		$off   = $this->config();
		$html2 = AFS_Renderer::render_results( $off, AFS_Renderer::normalize_state( array( 's' => 'zzzznomatch' ), $off ), 'http://example.org/' )['html'];
		$this->assertStringNotContainsString( 'afs-recover', $html2, 'Off: the 2.0.0 empty state is unchanged' );

		$nofilter = $this->config( array( 'show_chips' => 'true', 'post_type' => 'post', 'terms' => 'no-such-term-xyz' ) );
		$html3    = AFS_Renderer::render_results( $nofilter, AFS_Renderer::normalize_state( array(), $nofilter ), 'http://example.org/' )['html'];
		$this->assertStringNotContainsString( 'afs-recover', $html3, 'Nothing to clear when no visitor filter is active' );
	}

	public function test_block_attributes_map_to_the_new_options() {
		$html = AFS_Frontend::render_block( array( 'showSort' => 'show', 'showChips' => 'show' ) );
		$this->assertStringContainsString( 'name="afs_sort"', $html );
	}

	/** The bug the browser tests found: chips vanished after an ajax update because the REST fragment did not know they were on. */
	public function test_the_rest_fragment_renders_chips_when_the_script_passes_the_flag_on() {
		$with = $this->rest( array( 'afs_term' => 'news', 'show_chips' => '1', 'posts_per_page' => 5 ) )->get_data()['html'];
		$this->assertStringContainsString( 'afs-chips', $with );
		$without = $this->rest( array( 'afs_term' => 'news', 'posts_per_page' => 5 ) )->get_data()['html'];
		$this->assertStringNotContainsString( 'afs-chips', $without );
	}

	public function test_the_widget_config_carries_the_flag_only_when_chips_are_on() {
		$on = do_shortcode( '[ajax_filter_search show_chips="true"]' );
		$this->assertStringContainsString( '&quot;show_chips&quot;:&quot;1&quot;', $on );
		$this->reset_widget_counters();
		$this->assertStringNotContainsString( 'show_chips', do_shortcode( '[ajax_filter_search]' ), 'Existing feeds keep their exact markup' );
	}
}
