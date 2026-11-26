<?php
/**
 * The designs that have their own item markup (Editorial Grid, Resource Library) and the card options behind them.
 * Classic and Modern output is frozen by the 2.0.0 snapshots; these tests cover what is new and that the new options
 * never reach a Classic or Modern feed.
 */
class CardsAndDesignsTest extends AFS_Test_Case {

	public function set_up() {
		parent::set_up();
		delete_option( AFS_Design::OPTION );
		delete_option( AFS_SETTINGS );
		AFS_Registry::reset();
	}

	public function tear_down() {
		remove_all_filters( 'post_thumbnail_html' );
		AFS_Registry::reset();
		parent::tear_down();
	}

	private function client_config( $shortcode ) {
		$this->reset_widget_counters();
		$this->assertSame( 1, preg_match( '/data-afs-config="([^"]*)"/', do_shortcode( $shortcode ), $m ), 'Feed not rendered' );
		return json_decode( html_entity_decode( $m[1] ), true );
	}

	private function results( array $params ) {
		return $this->rest( $params )->get_data();
	}

	/** Give a post a featured image whose HTML is a fixed marker, so output can be searched for it. */
	private function give_image( $post_id ) {
		$attachment = self::factory()->attachment->create_object( 'cover.jpg', $post_id, array( 'post_mime_type' => 'image/jpeg' ) );
		set_post_thumbnail( $post_id, $attachment );
		AFS_Cache::flush(); // The version is written at shutdown in a real request; a test acts as the next one.
		add_filter(
			'post_thumbnail_html',
			static function () {
				return '<img src="https://example.test/cover.jpg" alt="" data-mark="COVERIMG">';
			}
		);
	}

	// --- Classic and Modern stay as they were ---

	public function test_classic_and_modern_send_no_design_or_card_options_to_the_script() {
		foreach ( array( 'classic', 'modern' ) as $design ) {
			$config = $this->client_config( '[ajax_filter_search design="' . $design . '" show_image="true" columns="2"]' );
			foreach ( array( 'design', 'show_image', 'columns', 'image_ratio', 'excerpt_length' ) as $key ) {
				$this->assertArrayNotHasKey( $key, $config, $design . ' must not send ' . $key );
			}
		}
	}

	public function test_classic_items_keep_the_table_header_and_row_classes() {
		$this->reset_widget_counters();
		$html = do_shortcode( '[ajax_filter_search design="classic" show_image="true"]' );
		$this->assertStringContainsString( 'afs-TableHeader', $html );
		$this->assertStringContainsString( 'afs-TableRowItem', $html );
		$this->assertStringNotContainsString( 'afs-card__body', $html );
		$this->assertStringNotContainsString( 'COVERIMG', $html );
	}

	// --- Option validation ---

	public function test_card_options_have_safe_defaults() {
		$c = AFS_Renderer::normalize_config( array( 'design' => 'editorial' ) );
		$this->assertTrue( $c['show_image'] );
		$this->assertSame( '16:10', $c['image_ratio'] );
		$this->assertSame( 3, $c['columns'] );
		$this->assertTrue( $c['show_excerpt'] );
		$this->assertSame( 25, $c['excerpt_length'] );
		$this->assertTrue( $c['show_date'] );
		$this->assertFalse( $c['show_time'] );
		$this->assertTrue( $c['show_category'] );

		$classic = AFS_Renderer::normalize_config( array( 'design' => 'classic' ) );
		$this->assertFalse( $classic['show_image'], 'Images are off unless the design asks for them or the feed does' );
	}

	public function test_invalid_card_options_fall_back_and_never_pass_through() {
		$c = AFS_Renderer::normalize_config(
			array(
				'design'         => 'editorial',
				'image_ratio'    => '1:1;}</style><script>alert(1)</script>',
				'columns'        => '99',
				'excerpt_length' => '100000',
			)
		);
		$this->assertSame( '16:10', $c['image_ratio'] );
		$this->assertSame( 4, $c['columns'] );
		$this->assertSame( 80, $c['excerpt_length'] );

		$c = AFS_Renderer::normalize_config( array( 'design' => 'editorial', 'columns' => '-2', 'excerpt_length' => '1', 'image_ratio' => 'wide' ) );
		$this->assertSame( 2, $c['columns'], 'absint turns -2 into 2, still inside the allowed range' );
		$this->assertSame( 10, $c['excerpt_length'] );
		$this->assertSame( '16:10', $c['image_ratio'] );

		$c = AFS_Renderer::normalize_config( array( 'design' => 'editorial', 'image_ratio' => '4/3' ) );
		$this->assertSame( '4:3', $c['image_ratio'], 'A slash is accepted for a ratio' );
	}

	public function test_only_the_known_designs_are_valid_and_unknown_falls_back() {
		$this->assertTrue( AFS_Design::is_valid( 'editorial' ) );
		$this->assertTrue( AFS_Design::is_valid( 'library' ) );
		$this->assertFalse( AFS_Design::is_valid( 'nonsense' ) );
		$this->assertSame( 'classic', AFS_Renderer::normalize_config( array( 'design' => 'nonsense' ) )['design'] );
		$this->assertSame( array( 'modern', 'classic', 'editorial', 'library' ), array_keys( AFS_Design::all() ), 'The picker and the block offer all four' );
	}

	public function test_a_design_with_its_own_markup_starts_in_its_own_layout_unless_the_feed_chooses() {
		$this->assertSame( 'grid', AFS_Renderer::normalize_config( array( 'design' => 'editorial' ) )['layout'] );
		$this->assertSame( 'list', AFS_Renderer::normalize_config( array( 'design' => 'library' ) )['layout'] );
		$this->assertSame( 'list', AFS_Renderer::normalize_config( array( 'design' => 'editorial', 'layout' => 'list' ) )['layout'] );
		$this->assertSame( 'list', AFS_Renderer::normalize_config( array( 'design' => 'classic' ) )['layout'], 'Classic still follows the setting' );
	}

	// --- First page equals the REST fragment ---

	public function test_the_first_page_and_the_rest_route_render_the_same_items_for_each_new_design() {
		foreach ( array(
			'[ajax_filter_search design="editorial" columns="2" image_ratio="4:3" excerpt_length="12" show_time="true" posts_per_page="5"]',
			'[ajax_filter_search design="library" show_excerpt="false" show_category="false" posts_per_page="5"]',
		) as $shortcode ) {
			$client = $this->client_config( $shortcode );
			$this->assertArrayHasKey( 'design', $client );

			$atts   = shortcode_parse_atts( trim( substr( $shortcode, strlen( '[ajax_filter_search' ), -1 ) ) );
			$config = AFS_Renderer::normalize_config( $atts );
			$base   = AFS_Renderer::current_url();
			$page   = AFS_Renderer::render_results( $config, AFS_Renderer::normalize_state( array(), $config ), $base, 1 );

			$params                 = $client;
			$params['base_url']     = $base;
			$params['instance']     = 1;
			$params['afs_page']     = 1;
			$params['afs_term']     = '';
			unset( $params['layout'] );
			$data = $this->results( $params );

			$this->assertSame( $this->normalize( $page['html'] ), $this->normalize( $data['html'] ), 'Page load and REST differ for ' . $shortcode );
		}
	}

	public function test_a_feed_without_the_options_in_the_request_would_differ_which_is_why_they_are_sent() {
		$client = $this->client_config( '[ajax_filter_search design="editorial" show_excerpt="false"]' );
		$this->assertSame( '0', $client['show_excerpt'] );
		$this->assertSame( 'editorial', $client['design'] );
		$with    = $this->results( array_merge( $client, array( 'posts_per_page' => 3 ) ) );
		$without = $this->results( array( 'posts_per_page' => 3 ) );
		$this->assertStringNotContainsString( 'afs-excerpt', $with['html'] );
		$this->assertStringContainsString( 'afs-TableRowItem', $without['html'], 'Without the design the route renders the default markup' );
	}

	public function test_card_options_are_part_of_the_cache_validator() {
		$a = $this->rest( array( 'design' => 'editorial', 'excerpt_length' => 12, 'posts_per_page' => 3 ) )->get_headers()['ETag'];
		$b = $this->rest( array( 'design' => 'editorial', 'excerpt_length' => 40, 'posts_per_page' => 3 ) )->get_headers()['ETag'];
		$this->assertNotSame( $a, $b );
	}

	// --- Markup of the new designs ---

	public function test_editorial_items_have_a_card_body_and_the_chosen_parts() {
		$html = $this->results(
			array(
				'design'        => 'editorial',
				'show_image'    => '1',
				'show_excerpt'  => '1',
				'show_category' => '1',
				'show_date'     => '1',
				'show_time'     => '0',
				'afs_term'      => 'news',
				'afs_year'      => 2024,
				'afs_month'     => 1,
			)
		)['html'];
		$this->assertStringContainsString( 'afs-card', $html );
		$this->assertStringContainsString( 'afs-card__body', $html );
		$this->assertStringNotContainsString( 'afs-TableHeader', $html );
		$this->assertStringContainsString( 'afs-badge', $html );
		$this->assertStringContainsString( 'afs-excerpt', $html );
		$this->assertStringContainsString( '<time class="afs-date"', $html );
		$this->assertStringNotContainsString( 'afs-time', $html );
		$this->assertStringContainsString( 'afs-img--none', $html, 'A post with no image gets the placeholder tile' );
	}

	public function test_the_excerpt_is_cut_by_words_and_can_be_turned_off() {
		$long = self::factory()->post->create( array( 'post_title' => 'Long one', 'post_excerpt' => '', 'post_content' => str_repeat( 'word ', 60 ) ) );
		$GLOBALS['post'] = get_post( $long );
		setup_postdata( $GLOBALS['post'] );
		$short = AFS_Card::excerpt( 10 );
		$this->assertSame( 10, count( preg_split( '/\s+/', $short ) ), 'Ten words, with the ellipsis attached to the last' );
		$this->assertStringEndsWith( '…', $short );
		$this->assertSame( 20, count( preg_split( '/\s+/', rtrim( AFS_Card::excerpt( 20 ), '…' ) ) ) );
		wp_reset_postdata();

		$html = $this->results( array( 'design' => 'editorial', 'show_excerpt' => '0', 'afs_year' => 2024, 'afs_month' => 1 ) )['html'];
		$this->assertStringNotContainsString( 'afs-excerpt', $html );
	}

	public function test_a_featured_image_is_shown_and_hidden_from_assistive_technology() {
		$this->give_image( 1001 );
		$html = $this->results( array( 'design' => 'editorial', 'show_image' => '1', 'afs_year' => 2024, 'afs_month' => 1 ) )['html'];
		$this->assertStringContainsString( 'COVERIMG', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html, 'The image link must not duplicate the title link' );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringNotContainsString( 'afs-img--none', $html );

		$off = $this->results( array( 'design' => 'editorial', 'show_image' => '0', 'afs_year' => 2024, 'afs_month' => 1 ) )['html'];
		$this->assertStringNotContainsString( 'COVERIMG', $off );
		$this->assertStringNotContainsString( 'afs-img', $off );
	}

	public function test_the_library_drops_the_thumbnail_column_for_a_post_without_an_image() {
		$none = $this->results( array( 'design' => 'library', 'show_image' => '1', 'afs_year' => 2024, 'afs_month' => 1 ) )['html'];
		$this->assertStringContainsString( 'afs-row--nothumb', $none );
		$this->assertStringNotContainsString( 'afs-img--none', $none, 'Rows have no placeholder tile' );

		$this->give_image( 1001 );
		$with = $this->results( array( 'design' => 'library', 'show_image' => '1', 'afs_year' => 2024, 'afs_month' => 1 ) )['html'];
		$this->assertStringContainsString( 'COVERIMG', $with );
		$this->assertStringNotContainsString( 'afs-row--nothumb', $with );
		$this->assertStringContainsString( 'afs-row__side', $with );
	}

	public function test_changing_a_featured_image_refreshes_cached_results() {
		$before = AFS_Cache::version();
		$attachment = self::factory()->attachment->create_object( 'a.jpg', 1002, array( 'post_mime_type' => 'image/jpeg' ) );
		AFS_Cache::flush();
		$after_create = AFS_Cache::version();
		set_post_thumbnail( 1002, $attachment );
		AFS_Cache::flush();
		$this->assertNotSame( $after_create, AFS_Cache::version(), 'Setting a featured image must change the cache version' );
		$this->assertNotSame( $before, AFS_Cache::version() );

		$mid = AFS_Cache::version();
		delete_post_meta( 1002, '_thumbnail_id' );
		AFS_Cache::flush();
		$this->assertNotSame( $mid, AFS_Cache::version(), 'Removing one must too' );

		$mid = AFS_Cache::version();
		update_post_meta( 1002, 'unrelated_key', 'x' );
		AFS_Cache::flush();
		$this->assertSame( $mid, AFS_Cache::version(), 'Other meta must not invalidate the cache' );
	}

	// --- Result bar, root element and stylesheets ---

	public function test_the_new_designs_group_chips_and_count_in_a_bar_above_the_items() {
		$html = $this->results( array( 'design' => 'editorial', 'show_chips' => '1', 'afs_term' => 'news', 'afs_year' => 2024, 'posts_per_page' => 5 ) )['html'];
		$this->assertStringContainsString( '<div class="afs-resultbar">', $html );
		$bar   = strpos( $html, 'afs-resultbar' );
		$chips = strpos( $html, 'afs-chips' );
		$count = strpos( $html, 'afs-count' );
		$items = strpos( $html, 'afs-items' );
		$this->assertTrue( $bar < $chips && $chips < $count && $count < $items, 'Chips, then the count, then the items' );
		$this->assertSame( 1, substr_count( $html, 'afs-count' ), 'The count is not repeated below the items' );
	}

	public function test_classic_and_modern_results_keep_the_count_after_the_items_and_have_no_result_bar() {
		foreach ( array( 'classic', 'modern' ) as $design ) {
			$html = $this->results( array( 'design' => $design, 'afs_year' => 2024, 'posts_per_page' => 5 ) )['html'];
			$this->assertStringNotContainsString( 'afs-resultbar', $html );
			$this->assertGreaterThan( strpos( $html, 'afs-items' ), strpos( $html, 'afs-count' ) );
		}
	}

	public function test_the_root_of_a_new_design_carries_its_classes_and_validated_css_variables() {
		$this->reset_widget_counters();
		$html = do_shortcode( '[ajax_filter_search design="editorial" columns="2" image_ratio="1:1"]' );
		$this->assertSame( 1, preg_match( '/<div id="afs-wrapper[^"]*" class="([^"]*)" style="([^"]*)"/', $html, $m ) );
		$this->assertStringContainsString( 'afs-design-editorial', $m[1] );
		$this->assertStringContainsString( 'afs-discovery', $m[1] );
		$this->assertSame( '--afs-cols:2;--afs-ratio:1 / 1;', $m[2] );

		$this->reset_widget_counters();
		$bad = do_shortcode( '[ajax_filter_search design="editorial" columns="9" image_ratio="red;background:url(x)"]' );
		$this->assertStringContainsString( 'style="--afs-cols:4;--afs-ratio:16 / 10;"', $bad, 'Hostile values never reach the style attribute' );

		foreach ( array( 'classic', 'modern' ) as $design ) {
			$this->reset_widget_counters();
			$this->assertStringNotContainsString( 'afs-discovery', do_shortcode( '[ajax_filter_search design="' . $design . '"]' ) );
			$this->reset_widget_counters();
			$this->assertDoesNotMatchRegularExpression( '/<div id="afs-wrapper[^"]*" class="[^"]*" style=/', do_shortcode( '[ajax_filter_search design="' . $design . '"]' ) );
		}
	}

	public function test_only_the_stylesheets_a_design_needs_are_loaded() {
		foreach ( array( 'afs-discovery', 'afs-editorial', 'afs-library', 'afs-modern' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
		$this->reset_widget_counters();
		do_shortcode( '[ajax_filter_search design="editorial"]' );
		$this->assertTrue( wp_style_is( 'afs-editorial', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'afs-discovery', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'afs-library', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'afs-modern', 'enqueued' ) );

		foreach ( array( 'afs-discovery', 'afs-editorial', 'afs-library', 'afs-modern' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
		$this->reset_widget_counters();
		do_shortcode( '[ajax_filter_search design="classic"]' );
		foreach ( array( 'afs-discovery', 'afs-editorial', 'afs-library' ) as $handle ) {
			$this->assertFalse( wp_style_is( $handle, 'enqueued' ), 'Classic must not load ' . $handle );
		}
	}

	// --- Protected and unpublished content ---

	public function test_a_password_protected_post_shows_neither_image_nor_excerpt() {
		$this->give_image( 2005 );
		foreach ( array( 'editorial', 'library' ) as $design ) {
			$html = $this->results( array( 'design' => $design, 'show_image' => '1', 'show_excerpt' => '1', 'afs_year' => 2026, 'afs_month' => 10 ) )['html'];
			$this->assertStringContainsString( 'UNPUB-password', $html, 'Its title is listed, as in any WordPress archive' );
			$this->assertStringNotContainsString( 'COVERIMG', $html, $design . ' leaked the image of a protected post' );
			$this->assertStringNotContainsString( 'BODYMARK-password', $html, $design . ' leaked the body of a protected post' );
			$this->assertStringNotContainsString( 'secret words', $html );
		}
	}

	public function test_unpublished_posts_stay_out_of_the_new_designs() {
		foreach ( array( 'editorial', 'library' ) as $design ) {
			$html = $this->results( array( 'design' => $design, 'afs_year' => 2026, 'afs_month' => 10, 'afs_year' => 2026 ) )['html'];
			foreach ( array( 'UNPUB-draft', 'UNPUB-pending', 'UNPUB-future', 'UNPUB-private' ) as $title ) {
				$this->assertStringNotContainsString( $title, $html );
			}
		}
	}

	public function test_a_hostile_design_or_option_in_the_request_cannot_break_the_markup() {
		$html = $this->results(
			array(
				'design'      => '"><script>alert(1)</script>',
				'image_ratio' => '"><img src=x onerror=alert(1)>',
				'columns'     => 3,
			)
		)['html'];
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
	}

	// --- Registered designs and overrides ---

	public function test_an_add_on_can_register_a_design_with_its_own_template() {
		$this->assertFalse( AFS_Design::is_valid( 'acme' ) );
		afs_register_design( 'acme', array( 'label' => 'Acme', 'template' => 'acme.php', 'item_class' => 'acme-item' ) );
		$this->assertTrue( AFS_Design::is_valid( 'acme' ) );
		$this->assertSame( 'acme.php', AFS_Design::template_for( 'acme' ) );
		$this->assertTrue( AFS_Design::has_own_markup( 'acme' ) );
		$this->assertSame( 'acme-item', AFS_Design::item_class( 'acme' ) );
		$this->assertSame( ' afs-design-acme', AFS_Design::root_class( 'acme' ) );
	}

	public function test_a_registered_design_cannot_replace_a_built_in_one_or_name_a_path_as_its_template() {
		afs_register_design( 'editorial', array( 'label' => 'Impostor', 'template' => 'impostor.php' ) );
		$this->assertSame( 'editorial.php', AFS_Design::template_for( 'editorial' ) );
		$this->assertNotSame( 'Impostor', AFS_Design::known()['editorial'] );

		afs_register_design( 'sneaky', array( 'template' => '../../wp-config.php' ) );
		$this->assertSame( 'default.php', AFS_Design::template_for( 'sneaky' ), 'A template name must be a plain file name' );
		$this->assertFalse( AFS_Design::has_own_markup( 'sneaky' ) );
	}

	public function test_a_theme_can_override_the_new_templates() {
		add_filter(
			'afs_template_file',
			static function ( $file, $template ) {
				return 'editorial.php' === $template ? dirname( __DIR__ ) . '/fixtures/override-editorial.php' : $file;
			},
			10,
			2
		);
		$html = $this->results( array( 'design' => 'editorial', 'afs_year' => 2024, 'afs_month' => 1 ) )['html'];
		$this->assertStringContainsString( 'OVERRIDDEN-EDITORIAL', $html );
	}

	public function test_return_template_keeps_working_with_the_1x_three_argument_call() {
		$GLOBALS['post'] = get_post( 1001 );
		setup_postdata( $GLOBALS['post'] );
		$html = return_template( 'default.php', array(), 'category' );
		wp_reset_postdata();
		$this->assertStringContainsString( 'Alpha report one', $html );
	}
}
