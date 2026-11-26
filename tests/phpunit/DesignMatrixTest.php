<?php
/**
 * Every site-default design with every feed design (16 combinations): the feed names its own design, so the first page and
 * the results route show the same items and nothing of the site's default leaks in. Also: the card images of a page of
 * results are loaded in a batch, not one query per post.
 */
class DesignMatrixTest extends AFS_Test_Case {

	const MARK = array(
		'classic'   => 'afs-TableRowItem',
		'modern'    => 'afs-TableRowItem',
		'editorial' => 'afs-card',
		'library'   => 'afs-row',
	);

	public function set_up() {
		parent::set_up();
		delete_option( AFS_SETTINGS );
	}

	public function tear_down() {
		delete_option( AFS_SETTINGS );
		parent::tear_down();
	}

	/** @return array<string,array<int,string>> */
	public function combinations() {
		$out = array();
		foreach ( array_keys( self::MARK ) as $site ) {
			foreach ( array_keys( self::MARK ) as $feed ) {
				$out[ "site $site, feed $feed" ] = array( $site, $feed );
			}
		}
		return $out;
	}

	private function normal( $html ) {
		return preg_replace( '/afs-summary-\d+|Post excerpt \d+/', 'N', preg_replace( '/\s+/', ' ', preg_replace( '/>\s+</', '><', $html ) ) );
	}

	/**
	 * @dataProvider combinations
	 */
	public function test_a_feed_keeps_its_own_design_on_the_page_and_through_the_results_route( $site, $feed ) {
		update_option( AFS_SETTINGS, array( 'afs_appearance_design' => $site ) );
		$this->reset_widget_counters();
		$page = do_shortcode( '[ajax_filter_search design="' . $feed . '" posts_per_page="5"]' );

		$this->assertStringContainsString( self::MARK[ $feed ], $page );
		foreach ( self::MARK as $design => $mark ) {
			if ( self::MARK[ $feed ] !== $mark ) {
				$this->assertStringNotContainsString( 'class="' . $mark, $page, "$design markup must not appear in a $feed feed on a $site site" );
			}
		}

		// The settings the page sends to the browser are exactly what the browser sends back.
		$this->assertSame( 1, preg_match( '/data-afs-config="([^"]*)"/', $page, $m ) );
		$config = json_decode( html_entity_decode( $m[1] ), true );
		$this->assertIsArray( $config );
		if ( isset( $config['design'] ) ) {
			$this->assertSame( $feed, $config['design'] );
		} else {
			// Nothing extra is sent only where the route would already use the same markup: Classic and Modern share it.
			$this->assertContains( $site, array( 'classic', 'modern' ) );
			$this->assertContains( $feed, array( 'classic', 'modern' ) );
		}
		$query = array(
			'afs_term'       => '',
			'posts_per_page' => 5,
			'post_type'      => $config['post_type'],
			'post_tax'       => $config['post_tax'],
			'orderby'        => $config['orderby'],
			'order'          => $config['order'],
			'layout'         => $config['layout'],
		);
		foreach ( $config as $key => $value ) {
			if ( ! isset( $query[ $key ] ) && 'terms' !== $key ) {
				$query[ $key ] = $value;
			}
		}
		$html = $this->rest( $query )->get_data()['html'];
		$this->assertStringContainsString( self::MARK[ $feed ], $html );
		$this->assertSame( substr_count( $page, 'class="' . self::MARK[ $feed ] ), substr_count( $html, 'class="' . self::MARK[ $feed ] ), 'The same number of items' );
	}

	public function test_a_page_of_cards_loads_its_images_in_a_batch() {
		$ids = get_posts( array( 'numberposts' => 12, 'fields' => 'ids', 'post_status' => 'publish' ) );
		foreach ( $ids as $id ) {
			if ( ! has_post_thumbnail( $id ) ) {
				set_post_thumbnail( $id, self::factory()->attachment->create_object( "c$id.jpg", $id, array( 'post_mime_type' => 'image/jpeg' ) ) );
			}
		}
		AFS_Cache::flush();
		$count = static function ( $per_page ) {
			global $wpdb;
			wp_cache_flush();
			$before = $wpdb->num_queries;
			AFS_Cache::flush();
			$GLOBALS['afs_instance_counter'] = 0;
			do_shortcode( '[ajax_filter_search design="editorial" show_image="true" posts_per_page="' . $per_page . '"]' );
			return $wpdb->num_queries - $before;
		};
		$small = $count( 3 );
		$large = $count( 10 );
		$this->assertLessThanOrEqual( $small + 4, $large, "7 more cards must not add a query each (3 cards: $small queries, 10 cards: $large)" );
	}
}
