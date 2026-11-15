<?php
/**
 * I3: the migration helper notices 1.x-era theme customizations, tells administrators once, and changes nothing.
 */
class MigrationNoticeTest extends AFS_Test_Case {

	private $dir;

	public function set_up() {
		parent::set_up();
		$this->dir = trailingslashit( sys_get_temp_dir() ) . 'afs-theme-' . wp_generate_password( 8, false );
		mkdir( $this->dir . '/afs-template', 0777, true );
		add_filter( 'stylesheet_directory', array( $this, 'theme_dir' ) );
		add_filter( 'template_directory', array( $this, 'theme_dir' ) );
	}

	public function tear_down() {
		remove_filter( 'stylesheet_directory', array( $this, 'theme_dir' ) );
		remove_filter( 'template_directory', array( $this, 'theme_dir' ) );
		foreach ( (array) glob( $this->dir . '/{afs-template/*,*}', GLOB_BRACE ) as $f ) {
			is_file( $f ) && unlink( $f );
		}
		@rmdir( $this->dir . '/afs-template' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@rmdir( $this->dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		unset( $_GET['afs_dismiss_migration'], $_GET['_wpnonce'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function theme_dir() {
		return $this->dir;
	}

	private function notice_html( $screen = 'plugins' ) {
		set_current_screen( $screen );
		ob_start();
		AFS_Migration::notice();
		return ob_get_clean();
	}

	private function admin() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_a_clean_theme_shows_nothing() {
		$this->admin();
		$this->assertSame( array( 'overrides' => array(), 'css' => array() ), AFS_Migration::detect() );
		$this->assertSame( '', $this->notice_html() );
	}

	public function test_a_template_override_is_detected_and_explained() {
		file_put_contents( $this->dir . '/afs-template/default.php', '<?php // override' );
		$found = AFS_Migration::detect();
		$this->assertCount( 1, $found['overrides'] );
		$this->assertStringContainsString( 'afs-template/default.php', $found['overrides'][0] );

		$this->admin();
		$html = $this->notice_html();
		$this->assertStringContainsString( 'keeps working', $html );
		$this->assertStringContainsString( 'MIGRATION-1.x-to-2.0.md', $html );
		$this->assertStringContainsString( 'afs_dismiss_migration', $html );
	}

	public function test_1x_ids_in_the_theme_stylesheet_are_detected() {
		file_put_contents( $this->dir . '/style.css', '#newsPanelResults .item { color: red; }' );
		$this->assertCount( 1, AFS_Migration::detect()['css'] );
		file_put_contents( $this->dir . '/style.css', '.afs-item { color: red; }' );
		$this->assertSame( array(), AFS_Migration::detect()['css'], 'Selectors that still exist in 2.0 are not flagged' );
	}

	public function test_only_administrators_on_the_plugin_screens_see_it() {
		file_put_contents( $this->dir . '/afs-template/default.php', '<?php' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( '', $this->notice_html() );

		$this->admin();
		$this->assertSame( '', $this->notice_html( 'dashboard' ), 'Not on unrelated screens' );
		$this->assertNotSame( '', $this->notice_html( 'settings_page_' . AFS_MENU_SLUG ) );
	}

	public function test_dismissing_hides_it_until_the_findings_change() {
		file_put_contents( $this->dir . '/afs-template/default.php', '<?php' );
		$this->admin();
		$this->assertNotSame( '', $this->notice_html() );

		$_GET['afs_dismiss_migration'] = '1';
		$_GET['_wpnonce']              = wp_create_nonce( 'afs_dismiss_migration' );
		add_filter( 'wp_redirect', '__return_false' ); // Stop the redirect; exit() cannot be tested, so call the storing step directly.
		update_user_meta( get_current_user_id(), AFS_Migration::USER_META, md5( wp_json_encode( AFS_Migration::detect() ) ) );
		remove_filter( 'wp_redirect', '__return_false' );

		$this->assertSame( '', $this->notice_html() );

		file_put_contents( $this->dir . '/afs-template/extra.php', '<?php' );
		$this->assertNotSame( '', $this->notice_html(), 'A new override brings the notice back' );
	}

	public function test_nothing_is_changed_by_looking() {
		file_put_contents( $this->dir . '/afs-template/default.php', '<?php // keep me' );
		$this->admin();
		$this->notice_html();
		$this->assertSame( '<?php // keep me', file_get_contents( $this->dir . '/afs-template/default.php' ) );
	}

	/** The accepting branch ends in exit and cannot run in-process; the rejecting branches can. */
	public function test_dismiss_handler_ignores_bad_nonces_and_non_admins() {
		file_put_contents( $this->dir . '/afs-template/default.php', '<?php' );
		$this->admin();
		$_GET['afs_dismiss_migration'] = '1';
		$_GET['_wpnonce']              = 'not-a-nonce';
		AFS_Migration::maybe_dismiss(); // Would exit if it accepted.
		$this->assertSame( '', (string) get_user_meta( get_current_user_id(), AFS_Migration::USER_META, true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_GET['_wpnonce'] = wp_create_nonce( 'afs_dismiss_migration' );
		AFS_Migration::maybe_dismiss();
		$this->assertSame( '', (string) get_user_meta( get_current_user_id(), AFS_Migration::USER_META, true ) );
	}
}
