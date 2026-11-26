<?php
/**
 * Block patterns (2.3.0): one configured feed per new design, built only from attributes the block already has.
 */
class PatternsTest extends AFS_Test_Case {

	public function tear_down() {
		remove_all_filters( 'afs_register_patterns' );
		foreach ( array( 'editorial-grid', 'resource-library' ) as $slug ) {
			unregister_block_pattern( 'ajax-filter-search/' . $slug );
		}
		if ( WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( AFS_Patterns::CATEGORY ) ) {
			unregister_block_pattern_category( AFS_Patterns::CATEGORY );
		}
		parent::tear_down();
	}

	private function registry() {
		return WP_Block_Patterns_Registry::get_instance();
	}

	public function test_each_new_design_has_a_pattern_in_the_plugins_category() {
		AFS_Patterns::register();
		foreach ( array( 'editorial-grid', 'resource-library' ) as $slug ) {
			$name = 'ajax-filter-search/' . $slug;
			$this->assertTrue( $this->registry()->is_registered( $name ), $name );
			$pattern = $this->registry()->get_registered( $name );
			$this->assertSame( array( AFS_Patterns::CATEGORY ), $pattern['categories'] );
			$this->assertNotSame( '', $pattern['title'] );
			$this->assertNotSame( '', $pattern['description'] );
		}
		$this->assertTrue( WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( AFS_Patterns::CATEGORY ) );
	}

	public function test_a_pattern_is_one_feed_block_that_uses_the_matching_design() {
		AFS_Patterns::register();
		foreach ( array( 'editorial-grid' => 'editorial', 'resource-library' => 'library' ) as $slug => $design ) {
			$blocks = parse_blocks( $this->registry()->get_registered( 'ajax-filter-search/' . $slug )['content'] );
			$blocks = array_values( array_filter( $blocks, static function ( $b ) { return ! empty( $b['blockName'] ); } ) );
			$this->assertCount( 1, $blocks );
			$this->assertSame( 'ajax-filter-search/feed', $blocks[0]['blockName'] );
			$this->assertSame( $design, $blocks[0]['attrs']['design'] );
		}
	}

	public function test_every_pattern_attribute_exists_in_the_block_and_has_the_right_type() {
		$json = json_decode( file_get_contents( AFS_PLUGIN_PATH . 'blocks/feed/block.json' ), true );
		foreach ( AFS_Patterns::definitions() as $slug => $pattern ) {
			foreach ( $pattern['attributes'] as $name => $value ) {
				$this->assertArrayHasKey( $name, $json['attributes'], $slug . ' uses an attribute the block does not have: ' . $name );
				$type = $json['attributes'][ $name ]['type'];
				if ( 'number' === $type ) {
					$this->assertIsNumeric( $value, $slug . '.' . $name . ' is not a number' );
				} elseif ( 'boolean' === $type ) {
					$this->assertIsBool( $value, $slug . '.' . $name . ' is not a boolean' );
				} else {
					$this->assertIsString( $value, $slug . '.' . $name . ' is not a string' );
				}
				if ( 'string' === $type && in_array( $name, array( 'showSort', 'showChips', 'showViews' ), true ) ) {
					$this->assertContains( $value, array( 'show', 'hide' ), $slug . '.' . $name );
				}
			}
		}
	}

	public function test_a_pattern_renders_through_the_block_as_its_design() {
		AFS_Patterns::register();
		$cases = array(
			'editorial-grid'   => array( 'afs-design-editorial', 'afs-card__body', '--afs-cols:3' ),
			'resource-library' => array( 'afs-design-library', 'afs-row__main', 'afs-count' ),
		);
		foreach ( $cases as $slug => $needles ) {
			$this->reset_widget_counters();
			$html = do_blocks( $this->registry()->get_registered( 'ajax-filter-search/' . $slug )['content'] );
			foreach ( $needles as $needle ) {
				$this->assertStringContainsString( $needle, $html, $slug . ' should contain ' . $needle );
			}
		}
	}

	public function test_the_pattern_options_reach_the_markup() {
		AFS_Patterns::register();
		$this->reset_widget_counters();
		$html = do_blocks( $this->registry()->get_registered( 'ajax-filter-search/editorial-grid' )['content'] );
		$this->assertStringContainsString( 'afs-field-sort', $html, 'The sort control is on' );
		$this->assertSame( 6, substr_count( $html, 'class="afs-item afs-card' ), 'Six cards' );
		$this->assertStringContainsString( 'data-afs-layout="grid"', $html, 'The grid keeps its layout switch' );

		$this->reset_widget_counters();
		$html = do_blocks( $this->registry()->get_registered( 'ajax-filter-search/resource-library' )['content'] );
		$this->assertSame( 10, substr_count( $html, 'class="afs-item afs-row' ), 'Ten rows' );
		$this->assertStringNotContainsString( 'data-afs-layout', $html, 'The layout switch is hidden for the library' );
	}

	public function test_the_patterns_can_be_turned_off() {
		add_filter( 'afs_register_patterns', '__return_false' );
		AFS_Patterns::register();
		$this->assertFalse( $this->registry()->is_registered( 'ajax-filter-search/editorial-grid' ) );
		$this->assertFalse( $this->registry()->is_registered( 'ajax-filter-search/resource-library' ) );
	}

	public function test_registering_twice_does_not_fail() {
		AFS_Patterns::register();
		AFS_Patterns::register();
		$this->assertTrue( $this->registry()->is_registered( 'ajax-filter-search/editorial-grid' ) );
	}

	public function test_the_content_is_valid_block_markup_with_json_attributes() {
		$content = AFS_Patterns::content( array( 'design' => 'editorial', 'perPage' => 6 ) );
		$this->assertSame( '<!-- wp:ajax-filter-search/feed {"design":"editorial","perPage":6} /-->', $content );
	}
}
