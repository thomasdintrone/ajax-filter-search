<?php
/**
 * The query choke point and the public extension API (registries, version, response filter).
 */
class ExtensionApiTest extends AFS_Test_Case {

	public function tear_down() {
		AFS_Registry::reset();
		remove_all_filters( 'afs_rest_response' );
		remove_all_actions( 'afs_register' );
		parent::tear_down();
	}

	private function titles( $html ) {
		preg_match_all( '/<h3><a [^>]*>([^<]*)<\/a><\/h3>/', $html, $m );
		return $m[1];
	}

	public function widening_provider() {
		return array(
			'any'             => array( array( 'post_status' => 'any' ) ),
			'list of states'  => array( array( 'post_status' => array( 'publish', 'draft', 'private', 'future', 'pending' ) ) ),
			'perm readable'   => array( array( 'post_status' => 'private', 'perm' => 'readable' ) ),
			'not an array'    => array( 'garbage' ),
		);
	}

	/** @dataProvider widening_provider */
	public function test_query_filter_cannot_expose_unpublished_content( $override ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'afs_query_args', static function ( $args ) use ( $override ) { return is_array( $override ) ? array_merge( $args, $override ) : $override; } );
		$data = $this->rest( array( 'afs_s' => 'UNPUB', 'posts_per_page' => 20 ) )->get_data();
		$this->assertStringNotContainsString( 'UNPUB-draft', $data['html'] );
		$this->assertStringNotContainsString( 'UNPUB-private', $data['html'] );
		$this->assertStringNotContainsString( 'UNPUB-future', $data['html'] );
		$this->assertStringNotContainsString( 'UNPUB-pending', $data['html'] );
	}

	public function test_query_filter_still_controls_everything_else() {
		add_filter( 'afs_query_args', static function ( $args ) { $args['orderby'] = 'title'; $args['order'] = 'ASC'; $args['post__not_in'] = array( 1001 ); return $args; } );
		$config = AFS_Renderer::normalize_config( array( 'posts_per_page' => 5 ) );
		$state  = AFS_Renderer::normalize_state( array(), $config );
		$titles = wp_list_pluck( AFS_Query::run( $config, $state )->posts, 'post_title' );
		$this->assertNotContains( 'Alpha report one', $titles );
		$sorted = $titles;
		sort( $sorted );
		$this->assertSame( $sorted, $titles );
	}

	public function test_renderer_query_delegates_to_the_choke_point() {
		$config = AFS_Renderer::normalize_config( array() );
		$state  = AFS_Renderer::normalize_state( array(), $config );
		$this->assertSame( wp_list_pluck( AFS_Query::run( $config, $state )->posts, 'ID' ), wp_list_pluck( AFS_Renderer::query( $config, $state )->posts, 'ID' ) );
	}

	public function test_api_version_is_an_integer_defined_outside_the_plugin_header() {
		$this->assertTrue( is_int( AFS_API_VERSION ) );
		$this->assertGreaterThanOrEqual( 1, AFS_API_VERSION );
	}

	/** A stand-in add-on registers one of each kind through the public API only. */
	public function test_addon_can_register_field_design_and_rest_meta() {
		add_action(
			'afs_register',
			static function () {
				afs_register_field( 'rating', array( 'label' => 'Rating' ) );
				afs_register_design( 'magazine', array( 'label' => 'Magazine' ) );
				afs_register_rest_meta( 'rating', array( 'type' => 'integer' ) );
				afs_register_template_part( 'badge', array( 'file' => '/x.php' ) );
				afs_register_admin_tab( 'license', array( 'label' => 'Add-on' ) );
			}
		);
		do_action( 'afs_register' );
		$this->assertSame( array( 'label' => 'Rating' ), AFS_Registry::get( 'fields', 'rating' ) );
		$this->assertArrayHasKey( 'magazine', afs_registered( 'designs' ) );
		$this->assertSame( array( 'type' => 'integer' ), AFS_Registry::get( 'rest_meta', 'rating' ) );
		$this->assertArrayHasKey( 'badge', afs_registered( 'template_parts' ) );
		$this->assertArrayHasKey( 'license', afs_registered( 'admin_tabs' ) );
	}

	public function test_registry_rejects_bad_input_and_duplicates() {
		$this->assertFalse( AFS_Registry::register( 'nonsense', 'a' ) );
		$this->assertFalse( AFS_Registry::register( 'fields', '!!!' ) );
		$this->assertTrue( afs_register_field( 'one', array( 'v' => 1 ) ) );
		$this->assertFalse( afs_register_field( 'one', array( 'v' => 2 ) ), 'A second registration must not overwrite the first' );
		$this->assertSame( array( 'v' => 1 ), AFS_Registry::get( 'fields', 'one' ) );
		AFS_Registry::unregister( 'fields', 'one' );
		$this->assertNull( AFS_Registry::get( 'fields', 'one' ) );
		$this->assertSame( array(), afs_registered( 'nonsense' ) );
	}

	public function test_registrations_do_not_change_default_output() {
		$before = $this->rest( array( 'posts_per_page' => 5 ) )->get_data();
		afs_register_field( 'rating', array() );
		afs_register_design( 'magazine', array() );
		afs_register_rest_meta( 'rating', array() );
		$after = $this->rest( array( 'posts_per_page' => 5 ) )->get_data();
		$this->assertSame( $this->normalize( $before['html'] ), $this->normalize( $after['html'] ) );
		$this->assertSame( $before['found'], $after['found'] );
		$this->assertSame( $before['pages'], $after['pages'] );
	}

	public function test_rest_response_shape_and_filter() {
		$default = $this->rest( array( 'posts_per_page' => 5 ) )->get_data();
		$keys    = array_keys( $default );
		sort( $keys );
		$this->assertSame( array( 'found', 'html', 'meta', 'pages' ), $keys, 'The 2.0.0 keys stay; meta is additive' );

		add_filter( 'afs_rest_response', static function ( $data, $config, $state ) { $data['extra'] = $config['per_page'] . ':' . $state['paged']; return $data; }, 10, 3 );
		$data = $this->rest( array( 'posts_per_page' => 5 ) )->get_data();
		$this->assertSame( '5:1', $data['extra'] );
		$this->assertSame( $this->normalize( $default['html'] ), $this->normalize( $data['html'] ) );
	}

	public function test_filter_type_is_defined_by_the_compat_layer_only_when_enabled() {
		$this->assertTrue( defined( 'FILTER_TYPE' ), 'The test bootstrap defines it once so output does not depend on order' );
		$this->assertTrue( method_exists( 'AFS_Compat', 'define_filter_type' ) );
		$this->assertStringNotContainsString( "define( 'FILTER_TYPE'", file_get_contents( AFS_PLUGIN_PATH . 'includes/class-afs-renderer.php' ), 'The renderer no longer defines the constant itself' );
	}
}
