<?php
/**
 * The Cards settings and the block (2.3.0): site-wide defaults for the card options, how a feed overrides them, the new
 * designs in the picker, and the block's attributes and preview.
 */
class SettingsAndBlockTest extends AFS_Test_Case {

	public function set_up() {
		parent::set_up();
		delete_option( AFS_SETTINGS );
		delete_option( AFS_Design::OPTION );
	}

	public function tear_down() {
		delete_option( AFS_SETTINGS );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function save( array $changes ) {
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), $changes ) );
	}

	// --- Defaults and sanitising ---

	public function test_the_card_settings_default_to_what_the_options_did_before_they_were_settings() {
		$d = AFSAdmin::defaults();
		$this->assertSame( 1, $d['afs_cards_show_image'] );
		$this->assertSame( '16:10', $d['afs_cards_image_ratio'] );
		$this->assertSame( 3, $d['afs_cards_columns'] );
		$this->assertSame( 1, $d['afs_cards_show_excerpt'] );
		$this->assertSame( 25, $d['afs_cards_excerpt_length'] );
		$this->assertSame( 1, $d['afs_cards_show_date'] );
		$this->assertSame( 0, $d['afs_cards_show_time'] );
		$this->assertSame( 1, $d['afs_cards_show_category'] );
	}

	public function test_a_site_that_saved_its_settings_before_2_3_0_gets_the_card_defaults_without_a_migration() {
		update_option( AFS_SETTINGS, array( 'afs_general_posts_per_page' => 7 ) );
		$c = AFS_Renderer::normalize_config( array( 'design' => 'editorial' ) );
		$this->assertSame( 3, $c['columns'] );
		$this->assertSame( 25, $c['excerpt_length'] );
		$this->assertTrue( $c['show_image'] );
		$this->assertSame( 7, $c['per_page'], 'The site\'s own setting is untouched' );
	}

	public function test_saving_validates_every_card_setting() {
		$out = AFSAdmin::sanitize(
			array(
				'afs_cards_show_image'     => '0',
				'afs_cards_image_ratio'    => '4/3',
				'afs_cards_columns'        => '2',
				'afs_cards_show_excerpt'   => '0',
				'afs_cards_excerpt_length' => '40',
				'afs_cards_show_date'      => '0',
				'afs_cards_show_time'      => '1',
				'afs_cards_show_category'  => '0',
			)
		);
		$this->assertSame( 0, $out['afs_cards_show_image'] );
		$this->assertSame( '4:3', $out['afs_cards_image_ratio'] );
		$this->assertSame( 2, $out['afs_cards_columns'] );
		$this->assertSame( 0, $out['afs_cards_show_excerpt'] );
		$this->assertSame( 40, $out['afs_cards_excerpt_length'] );
		$this->assertSame( 0, $out['afs_cards_show_date'] );
		$this->assertSame( 1, $out['afs_cards_show_time'] );
		$this->assertSame( 0, $out['afs_cards_show_category'] );
	}

	public function test_saving_hostile_card_values_falls_back_to_safe_ones() {
		$out = AFSAdmin::sanitize(
			array(
				'afs_cards_image_ratio'    => '"><script>alert(1)</script>',
				'afs_cards_columns'        => '9999',
				'afs_cards_excerpt_length' => '-5',
			)
		);
		$this->assertSame( '16:10', $out['afs_cards_image_ratio'] );
		$this->assertSame( 4, $out['afs_cards_columns'] );
		$this->assertSame( 10, $out['afs_cards_excerpt_length'] );
		$this->assertSame( array_keys( AFSAdmin::defaults() ), array_keys( $out ), 'Unknown keys are dropped and none is missing' );
	}

	// --- Settings drive the feed, the feed overrides the settings ---

	public function test_the_settings_are_the_defaults_for_a_new_design_feed() {
		$this->save( array( 'afs_cards_columns' => 2, 'afs_cards_excerpt_length' => 12, 'afs_cards_show_image' => 0, 'afs_cards_show_time' => 1, 'afs_cards_image_ratio' => '1:1' ) );
		$c = AFS_Renderer::normalize_config( array( 'design' => 'editorial' ) );
		$this->assertSame( 2, $c['columns'] );
		$this->assertSame( 12, $c['excerpt_length'] );
		$this->assertFalse( $c['show_image'] );
		$this->assertTrue( $c['show_time'] );
		$this->assertSame( '1:1', $c['image_ratio'] );
	}

	public function test_a_shortcode_attribute_beats_the_setting() {
		$this->save( array( 'afs_cards_columns' => 2, 'afs_cards_show_image' => 0 ) );
		$c = AFS_Renderer::normalize_config( array( 'design' => 'editorial', 'columns' => '4', 'show_image' => 'true' ) );
		$this->assertSame( 4, $c['columns'] );
		$this->assertTrue( $c['show_image'] );
	}

	public function test_classic_and_modern_ignore_the_card_settings() {
		$this->save( array( 'afs_cards_show_image' => 1, 'afs_cards_columns' => 4 ) );
		foreach ( array( 'classic', 'modern' ) as $design ) {
			$this->assertFalse( AFS_Renderer::normalize_config( array( 'design' => $design ) )['show_image'], $design . ' never shows images' );
		}
		$this->reset_widget_counters();
		$html = do_shortcode( '[ajax_filter_search design="classic"]' );
		$this->assertStringNotContainsString( 'afs-card__body', $html );
		$this->assertStringContainsString( 'afs-TableRowItem', $html );
	}

	public function test_a_site_default_of_editorial_applies_to_feeds_that_do_not_choose() {
		$this->save( array( 'afs_appearance_design' => 'editorial' ) );
		$this->reset_widget_counters();
		$html = do_shortcode( '[ajax_filter_search]' );
		$this->assertStringContainsString( 'afs-design-editorial', $html );
		$this->assertStringContainsString( 'afs-card__body', $html );
		$this->reset_widget_counters();
		$this->assertStringContainsString( 'afs-TableRowItem', do_shortcode( '[ajax_filter_search design="classic"]' ), 'A feed can still opt out' );
	}

	// --- The settings screen ---

	private function settings_html() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		AFSAdmin::options_page();
		return ob_get_clean();
	}

	public function test_the_picker_offers_the_new_designs_and_marks_the_chosen_one() {
		$this->save( array( 'afs_appearance_design' => 'library' ) );
		$html = $this->settings_html();
		foreach ( array( 'modern', 'classic', 'editorial', 'library' ) as $slug ) {
			$this->assertStringContainsString( 'value="' . $slug . '"', $html );
		}
		$this->assertMatchesRegularExpression( '/value="library" checked/', $html );
		$this->assertStringContainsString( 'Editorial Grid', $html );
		$this->assertStringContainsString( 'Resource Library', $html );
	}

	public function test_the_cards_group_shows_each_control_with_the_saved_value() {
		$this->save( array( 'afs_cards_columns' => 2, 'afs_cards_excerpt_length' => 33, 'afs_cards_image_ratio' => '3:2' ) );
		$html = $this->settings_html();
		$this->assertStringContainsString( 'id="afs-sec-cards"', $html );
		$this->assertMatchesRegularExpression( '/<option value="2" selected=\'selected\'>2<\/option>/', $html );
		$this->assertMatchesRegularExpression( '/<option value="3:2" selected=\'selected\'>3:2<\/option>/', $html );
		$this->assertStringContainsString( 'value="33"', $html );
	}

	// --- The block ---

	public function test_every_block_attribute_reaches_the_renderer() {
		$json  = json_decode( file_get_contents( AFS_PLUGIN_PATH . 'blocks/feed/block.json' ), true );
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'ajax-filter-search/feed' );
		$this->assertNotNull( $block );
		foreach ( array( 'showImage', 'imageRatio', 'columns', 'showExcerpt', 'excerptLength', 'showDate', 'showTime', 'showCategory', 'design' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $json['attributes'] );
			$this->assertArrayHasKey( $attr, $block->attributes );
		}
	}

	public function test_the_block_renders_a_new_design_with_its_card_options() {
		$this->reset_widget_counters();
		$html = render_block(
			array(
				'blockName' => 'ajax-filter-search/feed',
				'attrs'     => array(
					'design'        => 'editorial',
					'columns'       => 2,
					'imageRatio'    => '1:1',
					'showExcerpt'   => 'hide',
					'showTime'      => 'show',
					'excerptLength' => 0,
					'perPage'       => 4,
				),
			)
		);
		$this->assertStringContainsString( 'afs-design-editorial', $html );
		$this->assertStringContainsString( 'style="--afs-cols:2;--afs-ratio:1 / 1;"', $html );
		$this->assertStringNotContainsString( 'afs-excerpt', $html );
		$this->assertStringContainsString( 'afs-time', $html );
	}

	public function test_a_block_with_no_new_attributes_renders_exactly_as_before() {
		$this->reset_widget_counters();
		$old = render_block( array( 'blockName' => 'ajax-filter-search/feed', 'attrs' => array( 'perPage' => 4, 'showViews' => 'hide' ) ) );
		$this->reset_widget_counters();
		$new = render_block( array( 'blockName' => 'ajax-filter-search/feed', 'attrs' => array( 'perPage' => 4, 'showViews' => 'hide', 'columns' => 0, 'excerptLength' => 0, 'showImage' => '' ) ) );
		$this->assertSame( $this->normalize( $old ), $this->normalize( $new ) );
		$this->assertStringNotContainsString( 'afs-discovery', $old );
	}

	public function test_the_editor_gets_the_designs_the_site_default_and_the_taxonomy() {
		$handle = generate_block_asset_handle( 'ajax-filter-search/feed', 'editorScript' );
		$this->assertTrue( wp_script_is( $handle, 'registered' ) );
		$this->save( array( 'afs_appearance_design' => 'library', 'afs_general_post_taxonomy' => 'category' ) );
		AFS_Frontend::editor_assets();
		$before = wp_scripts()->get_data( $handle, 'before' );
		$js     = is_array( $before ) ? implode( '', $before ) : '';
		$this->assertStringContainsString( 'window.afsEditor', $js );
		$data = json_decode( substr( $js, strlen( 'window.afsEditor = ' ), -1 ), true );
		$this->assertSame( 'library', $data['siteDesign'] );
		$this->assertSame( 'category', $data['siteTaxonomy'] );
		$by = array_column( $data['designs'], 'own', 'value' );
		$this->assertSame( array( 'modern' => false, 'classic' => false, 'editorial' => true, 'library' => true ), $by );
	}

	public function test_the_block_preview_route_renders_a_new_design() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/ajax-filter-search/feed' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', array( 'design' => 'library', 'perPage' => 3 ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'afs-row__main', $response->get_data()['rendered'] );
	}

	// --- Progressive disclosure in the Cards settings ---

	public function test_a_dependent_row_is_hidden_when_its_parent_is_off_but_its_value_is_kept_and_submitted() {
		$this->save( array( 'afs_cards_show_image' => 0, 'afs_cards_image_ratio' => '3:2', 'afs_cards_show_excerpt' => 0, 'afs_cards_excerpt_length' => 33, 'afs_cards_show_date' => 0 ) );
		$html = $this->settings_html();
		foreach ( array( 'afs_cards_show_image', 'afs_cards_show_excerpt', 'afs_cards_show_date' ) as $parent ) {
			$this->assertMatchesRegularExpression( '/<div class="afs-row" data-afs-needs="afs_settings\[' . $parent . '\]" hidden>/', $html, $parent . ' dependents are hidden' );
		}
		// Hidden is not removed or disabled: the controls are still in the form with their saved values.
		$this->assertStringContainsString( 'name="afs_settings[afs_cards_image_ratio]"', $html );
		$this->assertMatchesRegularExpression( '/<option value="3:2" selected=\'selected\'>/', $html );
		$this->assertStringContainsString( 'value="33"', $html );
		$this->assertDoesNotMatchRegularExpression( '/data-afs-needs[^>]*disabled/', $html );
		$out = AFSAdmin::sanitize( array( 'afs_cards_show_image' => '0', 'afs_cards_image_ratio' => '3:2', 'afs_cards_show_excerpt' => '0', 'afs_cards_excerpt_length' => '33', 'afs_cards_show_date' => '0' ) );
		$this->assertSame( '3:2', $out['afs_cards_image_ratio'], 'The value survives a save while hidden' );
		$this->assertSame( 33, $out['afs_cards_excerpt_length'] );
	}

	public function test_a_dependent_row_shows_when_its_parent_is_on() {
		$this->save( array() );
		$html = $this->settings_html();
		$this->assertDoesNotMatchRegularExpression( '/data-afs-needs="afs_settings\[afs_cards_show_[a-z]+\]" hidden/', $html );
		$this->assertSame( 3, substr_count( $html, 'data-afs-needs=' ), 'Image shape, excerpt length and time' );
	}

	public function test_the_cards_card_is_open_for_a_site_on_a_new_design_and_folded_with_a_note_otherwise() {
		$this->save( array( 'afs_appearance_design' => 'editorial' ) );
		$this->assertMatchesRegularExpression( '/<details class="afs-fold" open><summary>Card settings<\/summary>/', $this->settings_html() );

		$this->save( array( 'afs_appearance_design' => 'modern' ) );
		$html = $this->settings_html();
		$this->assertMatchesRegularExpression( '/<details class="afs-fold"><summary>Card settings <small>Used by feeds that choose Editorial Grid or Resource Library\.<\/small>/', $html );
		foreach ( array( 'show_image', 'image_ratio', 'columns', 'show_excerpt', 'excerpt_length', 'show_date', 'show_time', 'show_category' ) as $key ) {
			$this->assertStringContainsString( 'name="afs_settings[afs_cards_' . $key . ']"', $html, 'Folded, but ' . $key . ' is still in the form' );
		}
	}

	public function test_the_cards_card_explains_defaults_and_overrides() {
		$html = $this->settings_html();
		$this->assertStringContainsString( 'class="afs-callout"', $html );
		$this->assertStringContainsString( 'Defaults, and how a feed overrides them.', $html );
		$this->assertStringContainsString( 'Use plugin setting', $html );
		$this->assertStringContainsString( 'Include the time', $html );
		$this->assertStringNotContainsString( 'Publication time', $html );
	}

	public function test_the_time_is_part_of_the_date() {
		$this->assertTrue( AFS_Renderer::normalize_config( array( 'design' => 'editorial', 'show_time' => 'true' ) )['show_time'] );
		$c = AFS_Renderer::normalize_config( array( 'design' => 'editorial', 'show_date' => 'false', 'show_time' => 'true' ) );
		$this->assertFalse( $c['show_date'] );
		$this->assertFalse( $c['show_time'], 'No date, no time' );

		$this->save( array( 'afs_cards_show_date' => 0, 'afs_cards_show_time' => 1 ) );
		$this->assertFalse( AFS_Renderer::normalize_config( array( 'design' => 'library' ) )['show_time'], 'The saved time is kept but not used without the date' );
		$this->assertSame( 1, AFSAdmin::afs_get_option( 'afs_cards_show_time' ), 'and it is still saved' );

		$html = $this->rest( array( 'design' => 'editorial', 'show_date' => '0', 'show_time' => '1', 'afs_year' => 2024, 'afs_month' => 1 ) )->get_data()['html'];
		$this->assertStringNotContainsString( 'afs-time', $html );
		$this->assertStringNotContainsString( 'afs-date', $html );
	}

	public function test_the_editor_is_told_the_current_card_defaults() {
		$this->save( array( 'afs_cards_show_image' => 0, 'afs_cards_columns' => 2, 'afs_cards_excerpt_length' => 40, 'afs_cards_image_ratio' => '1:1' ) );
		$handle = generate_block_asset_handle( 'ajax-filter-search/feed', 'editorScript' );
		wp_scripts()->add_data( $handle, 'before', array() );
		AFS_Frontend::editor_assets();
		$before = wp_scripts()->get_data( $handle, 'before' );
		$js     = is_array( $before ) ? implode( '', $before ) : '';
		$data   = json_decode( substr( $js, strlen( 'window.afsEditor = ' ), -1 ), true );
		$this->assertFalse( $data['cards']['showImage'] );
		$this->assertSame( 2, $data['cards']['columns'] );
		$this->assertSame( 40, $data['cards']['excerptLength'] );
		$this->assertSame( '1:1', $data['cards']['imageRatio'] );
		$this->assertTrue( $data['cards']['showDate'] );
		$this->assertFalse( $data['cards']['showTime'] );
	}
}
