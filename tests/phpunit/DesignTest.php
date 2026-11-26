<?php
/**
 * Designs (Classic and Modern) and the settings screen. Classic is the 2.0 markup (the frozen snapshots prove that);
 * these tests cover what Modern adds and how a design is chosen.
 */
class DesignTest extends AFS_Test_Case {

	public function set_up() {
		parent::set_up();
		delete_option( AFS_Design::OPTION );
		delete_option( AFS_SETTINGS );
		wp_dequeue_style( 'afs-modern' );
	}

	public function tear_down() {
		delete_option( AFS_Design::OPTION );
		delete_option( AFS_SETTINGS );
		wp_dequeue_style( 'afs-modern' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function root_class( $shortcode ) {
		$this->reset_widget_counters();
		preg_match( '/<div id="afs-wrapper[^"]*" class="([^"]*)"/', do_shortcode( $shortcode ), $m );
		return isset( $m[1] ) ? $m[1] : '';
	}

	public function test_a_site_that_already_has_the_plugin_stays_classic() {
		$this->assertSame( 'classic', AFS_Design::site_default() );
		$this->assertStringNotContainsString( 'afs-design-modern', $this->root_class( '[ajax_filter_search]' ) );
		$this->assertFalse( wp_style_is( 'afs-modern', 'enqueued' ), 'A Classic feed must not load the Modern stylesheet' );
	}

	public function test_a_new_install_gets_modern() {
		AFS_Design::activate();
		$this->assertSame( 'modern', get_option( AFS_Design::OPTION ) );
		$this->assertSame( 'modern', AFS_Design::site_default() );
		$this->assertStringContainsString( 'afs-design-modern', $this->root_class( '[ajax_filter_search]' ) );
		$this->assertTrue( wp_style_is( 'afs-modern', 'enqueued' ) );
	}

	public function test_activation_never_changes_a_site_that_already_has_settings_or_a_recorded_choice() {
		update_option( AFS_SETTINGS, array( 'afs_general_posts_per_page' => 7 ) );
		AFS_Design::activate();
		$this->assertFalse( get_option( AFS_Design::OPTION ), 'An existing site must not be switched to Modern by reactivating' );

		delete_option( AFS_SETTINGS );
		update_option( AFS_Design::OPTION, 'classic' );
		AFS_Design::activate();
		$this->assertSame( 'classic', get_option( AFS_Design::OPTION ) );
	}

	public function test_the_shortcode_attribute_overrides_the_site_default_both_ways() {
		$this->assertStringContainsString( 'afs-design-modern', $this->root_class( '[ajax_filter_search design="modern"]' ) );
		update_option( AFS_Design::OPTION, 'modern' );
		$this->assertStringNotContainsString( 'afs-design-modern', $this->root_class( '[ajax_filter_search design="classic"]' ) );
		$this->assertStringContainsString( 'afs-design-modern', $this->root_class( '[ajax_filter_search design="nonsense"]' ), 'An unknown value falls back to the site default' );
	}

	public function test_the_appearance_setting_is_used_when_the_feed_does_not_say() {
		update_option( AFS_Design::OPTION, 'modern' );
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_appearance_design' => 'classic' ) ) );
		$this->assertStringNotContainsString( 'afs-design-modern', $this->root_class( '[ajax_filter_search]' ) );
		$this->assertStringContainsString( 'afs-design-modern', $this->root_class( '[ajax_filter_search design="modern"]' ) );
	}

	public function test_classic_markup_is_identical_whichever_site_default_is_set() {
		$classic_default = do_shortcode( '[ajax_filter_search design="classic" posts_per_page="3"]' );
		$this->reset_widget_counters();
		update_option( AFS_Design::OPTION, 'modern' );
		$classic_on_modern_site = do_shortcode( '[ajax_filter_search design="classic" posts_per_page="3"]' );
		$this->assertSame( $this->normalize( $classic_default ), $this->normalize( $classic_on_modern_site ) );
	}

	public function test_modern_changes_only_the_class_not_the_markup_inside() {
		$classic = $this->normalize( do_shortcode( '[ajax_filter_search design="classic" posts_per_page="3"]' ) );
		$this->reset_widget_counters();
		$modern = $this->normalize( do_shortcode( '[ajax_filter_search design="modern" posts_per_page="3"]' ) );
		$this->assertSame( $classic, str_replace( ' afs-design-modern', '', $modern ) );
	}

	public function test_the_stylesheet_is_loaded_once_with_text_colour_that_reads_on_the_accent() {
		do_shortcode( '[ajax_filter_search design="modern"]' );
		$this->reset_widget_counters();
		do_shortcode( '[ajax_filter_search design="modern"]' );
		$this->assertTrue( wp_style_is( 'afs-modern', 'enqueued' ) );
		$after = implode( ' ', (array) wp_styles()->get_data( 'afs-modern', 'after' ) );
		$this->assertStringContainsString( '--afs-on-accent:', $after );
	}

	public function test_text_on_the_accent_is_white_or_dark_whichever_contrasts_more() {
		$this->assertSame( '#ffffff', AFS_Design::on_colour( '#2563eb' ) );
		$this->assertSame( '#ffffff', AFS_Design::on_colour( '#000' ) );
		$this->assertSame( '#111827', AFS_Design::on_colour( '#f1b45c' ), 'The classic orange needs dark text' );
		$this->assertSame( '#111827', AFS_Design::on_colour( '#ffffff' ) );
		$this->assertSame( '#ffffff', AFS_Design::on_colour( 'not a colour' ) );
	}

	public function test_default_accent_follows_the_site_design_and_a_saved_accent_wins() {
		$this->assertSame( AFSAdmin::DEFAULT_ACCENT, AFS_Design::default_accent() );
		update_option( AFS_Design::OPTION, 'modern' );
		$this->assertSame( AFS_Design::MODERN_ACCENT, AFS_Design::default_accent() );
		$this->assertSame( AFS_Design::MODERN_ACCENT, AFSAdmin::afs_get_option( 'afs_appearance_accent' ) );
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_appearance_accent' => '#0a7' ) ) );
		$this->assertSame( '#0a7', AFSAdmin::afs_get_option( 'afs_appearance_accent' ) );
	}

	public function test_the_design_setting_is_validated_on_save() {
		$this->assertSame( 'modern', AFSAdmin::sanitize( array( 'afs_appearance_design' => 'modern' ) )['afs_appearance_design'] );
		$this->assertSame( 'classic', AFSAdmin::sanitize( array( 'afs_appearance_design' => 'classic' ) )['afs_appearance_design'] );
		$this->assertSame( 'classic', AFSAdmin::sanitize( array( 'afs_appearance_design' => '<script>' ) )['afs_appearance_design'], 'Junk falls back to the site default' );
	}

	public function test_block_attribute_and_the_afs_feed_shortcode_follow_the_design() {
		update_option( AFS_Design::OPTION, 'modern' );
		$this->assertStringContainsString( 'afs-design-modern', AFS_Frontend::render_block( array( 'design' => 'modern' ) ) );
		$this->reset_widget_counters();
		$this->assertStringNotContainsString( 'afs-design-modern', AFS_Frontend::render_block( array( 'design' => 'classic' ) ) );
		$this->assertStringContainsString( 'afs-design-modern', do_shortcode( '[afs_feed design="modern"]' ) );
	}

	public function test_the_rest_fragment_does_not_depend_on_the_design() {
		$classic = $this->rest( array( 'posts_per_page' => 4, 'design' => 'classic' ) )->get_data()['html'];
		update_option( AFS_Design::OPTION, 'modern' );
		$modern = $this->rest( array( 'posts_per_page' => 4 ) )->get_data()['html'];
		$this->assertSame( $this->normalize( $classic ), $this->normalize( $modern ) );
	}

	/* The settings screen ----------------------------------------------------------------- */

	private function settings_html() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		AFSAdmin::options_page();
		return ob_get_clean();
	}

	public function test_the_settings_screen_has_a_card_for_each_group_and_every_field_once() {
		$html = $this->settings_html();
		$this->assertSame( 1, substr_count( $html, '<h1>' ) );
		foreach ( array( 'start', 'feed', 'filters', 'search', 'appearance', 'cards' ) as $id ) {
			$this->assertStringContainsString( 'id="afs-sec-' . $id . '"', $html );
			$this->assertStringContainsString( 'href="#afs-sec-' . $id . '"', $html );
		}
		foreach ( array_keys( AFSAdmin::fields() ) as $key ) {
			$this->assertSame( 1, substr_count( $html, 'name="afs_settings[' . $key . ']"' ) > 0 ? 1 : 0, 'Field missing from the screen: ' . $key );
		}
		$this->assertSame( 4, substr_count( $html, 'name="afs_settings[afs_appearance_design]"' ), 'One radio per design' );
		$this->assertStringContainsString( 'Save Settings', $html );
		$this->assertSame( 5, substr_count( $html, 'data-afs-copy=' ), 'Five copyable example shortcodes' );
	}

	public function test_labels_only_point_at_things_a_label_can_name() {
		$html = $this->settings_html();
		preg_match_all( '/<label for="(afs-[a-z_]+)"/', $html, $m );
		foreach ( $m[1] as $id ) {
			$this->assertMatchesRegularExpression( '/<(select|input)[^>]*id="' . preg_quote( $id, '/' ) . '"/', $html, "A label points at $id, which is not a form control" );
		}
		$this->assertStringContainsString( 'class="afs-row__title"', $html, 'Yes/No and design groups use a plain title; their legend names the fieldset' );
	}

	public function test_the_design_picker_marks_the_effective_design() {
		update_option( AFS_Design::OPTION, 'modern' );
		$this->assertMatchesRegularExpression( '/value="modern" checked/', $this->settings_html() );
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_appearance_design' => 'classic' ) ) );
		$this->assertMatchesRegularExpression( '/value="classic" checked/', $this->settings_html() );
	}

	public function test_the_settings_stylesheet_loads_only_on_the_plugins_own_screen() {
		wp_dequeue_style( 'afs-admin-style' );
		AFSAdmin::load_admin_scripts( 'plugins.php' );
		$this->assertFalse( wp_style_is( 'afs-admin-style', 'enqueued' ) );
		AFSAdmin::load_admin_scripts( 'settings_page_' . AFS_MENU_SLUG );
		$this->assertTrue( wp_style_is( 'afs-admin-style', 'enqueued' ) );
		$this->assertFileExists( AFS_PLUGIN_PATH . 'admin/css/settings.css' );
		$this->assertFileExists( AFS_PLUGIN_PATH . 'admin/images/icon.svg' );
		$this->assertFileExists( AFS_PLUGIN_PATH . 'public/css/afs-modern.css' );
	}

	/** A Classic feed with a dark accent must not keep dark button text (it would fail WCAG contrast). */
	public function test_classic_gets_white_button_text_only_when_the_accent_needs_it() {
		$inline = static function () {
			return implode( ' ', (array) wp_styles()->get_data( 'afs-styles', 'after' ) );
		};

		wp_styles()->add_data( 'afs-styles', 'after', array() );
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_appearance_accent' => '#2563eb' ) ) );
		do_shortcode( '[ajax_filter_search design="classic"]' );
		$this->assertStringContainsString( '--afs-on-accent:#ffffff', $inline() );

		$this->reset_widget_counters();
		wp_styles()->add_data( 'afs-styles', 'after', array() );
		update_option( AFS_SETTINGS, array_merge( AFSAdmin::defaults(), array( 'afs_appearance_accent' => '#f1b45c' ) ) );
		do_shortcode( '[ajax_filter_search design="classic"]' );
		$this->assertStringNotContainsString( '--afs-on-accent', $inline(), 'The default orange keeps the exact 2.x output' );
		$this->assertStringContainsString( '.afs{--afs-accent:#f1b45c;}', $inline() );
	}
}
