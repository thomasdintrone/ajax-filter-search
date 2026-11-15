<?php
/**
 * Shared fixtures and helpers for the Ajax Filter Search tests.
 *
 * Fixtures are fully deterministic (fixed IDs, dates, slugs, titles) so that rendered HTML can be compared
 * with snapshots generated from the 2.0.0 release tag.
 */
abstract class AFS_Test_Case extends WP_UnitTestCase {

	/** @var WP_REST_Server */
	protected $server;

	/** Published fixture posts: id => [title, category slugs, date, content]. */
	protected static function published_posts() {
		return array(
			1001 => array( 'Alpha report one', array( 'news' ), '2024-01-15 10:00:00', 'Alpha report one has a medium length body. ' . str_repeat( 'Lorem ipsum dolor sit amet. ', 4 ) ),
			1002 => array( 'Bravo update two', array( 'news', 'events' ), '2024-03-05 09:30:00', 'Bravo update two body text. ' . str_repeat( 'Consectetur adipiscing elit. ', 5 ) ),
			1003 => array( 'Charlie notice three', array( 'press-releases' ), '2024-03-20 14:00:00', 'Short body.' ),
			1004 => array( "Delta O'Brien four", array( 'events' ), '2024-07-04 08:00:00', 'Delta body mentions a quarterly result. ' . str_repeat( 'Sed do eiusmod tempor. ', 6 ) ),
			1005 => array( 'Echo "quoted" five', array( 'news' ), '2025-02-11 11:11:00', 'Echo body. ' . str_repeat( 'Ut enim ad minim veniam. ', 8 ) ),
			1006 => array( 'Foxtrot summary six', array( 'press-releases', 'news' ), '2025-03-03 12:00:00', 'Foxtrot body with <strong>markup</strong> and [shortcode] text. ' . str_repeat( 'Duis aute irure. ', 6 ) ),
			1007 => array( 'Golf guide seven', array( 'events' ), '2025-06-30 16:45:00', 'Golf body. ' . str_repeat( 'Excepteur sint occaecat. ', 5 ) ),
			1008 => array( 'Hotel report eight', array( 'news' ), '2025-10-10 10:10:00', 'Hotel report eight. ' . str_repeat( 'Nemo enim ipsam. ', 7 ) ),
			1009 => array( 'India notice nine', array( 'press-releases' ), '2026-01-02 07:00:00', 'India body. ' . str_repeat( 'Quis autem vel eum. ', 5 ) ),
			1010 => array( 'Juliet quarterly results ten', array( 'news', 'press-releases' ), '2026-03-15 13:00:00', 'Juliet quarterly results. ' . str_repeat( 'At vero eos et accusamus. ', 6 ) ),
			1011 => array( 'Kilo update eleven', array( 'events' ), '2026-03-28 15:30:00', 'Kilo body. ' . str_repeat( 'Temporibus autem quibusdam. ', 4 ) ),
			1012 => array( 'Lima report twelve', array(), '2026-05-05 09:00:00', 'Lima body without a category. ' . str_repeat( 'Itaque earum rerum. ', 5 ) ),
			1013 => array( 'Mike notice thirteen', array( 'news' ), '2026-08-19 18:00:00', 'Mike body. ' . str_repeat( 'Nam libero tempore. ', 5 ) ),
			1014 => array( 'November summary fourteen', array( 'press-releases' ), '2026-09-09 09:09:00', 'November body. ' . str_repeat( 'Cum soluta nobis. ', 6 ) ),
		);
	}

	public static function wpSetUpBeforeClass( $factory ) {
		update_option( 'timezone_string', 'UTC' );
		update_option( 'date_format', 'F j, Y' );
		update_option( 'time_format', 'g:i a' );
		delete_option( 'afs_settings' );

		foreach ( array( 'news' => 'News', 'press-releases' => 'Press Releases', 'events' => 'Events' ) as $slug => $name ) {
			$factory->category->create( array( 'name' => $name, 'slug' => $slug ) );
		}

		foreach ( self::published_posts() as $id => $p ) {
			$cats = array();
			foreach ( $p[1] as $slug ) {
				$cats[] = get_category_by_slug( $slug )->term_id;
			}
			$factory->post->create(
				array(
					'import_id'     => $id,
					'post_title'    => $p[0],
					'post_name'     => sanitize_title( $p[0] ),
					'post_status'   => 'publish',
					'post_date'     => $p[2],
					'post_content'  => $p[3],
					'post_category' => $cats ? $cats : array( 1 ),
				)
			);
		}

		// Content that must never appear in public output (except the password post's title, as in any WP archive).
		$news = get_category_by_slug( 'news' )->term_id;
		$base = array( 'post_content' => 'BODYMARK-%s secret words here.', 'post_category' => array( $news ), 'post_date' => '2026-10-01 10:00:00' );
		foreach (
			array(
				2001 => array( 'draft', array( 'post_status' => 'draft' ) ),
				2002 => array( 'pending', array( 'post_status' => 'pending' ) ),
				2003 => array( 'future', array( 'post_status' => 'future', 'post_date' => '2099-01-01 10:00:00' ) ),
				2004 => array( 'private', array( 'post_status' => 'private' ) ),
				2005 => array( 'password', array( 'post_status' => 'publish', 'post_password' => 'hunter2' ) ),
			) as $id => $def
		) {
			$factory->post->create(
				array_merge(
					$base,
					array(
						'import_id'    => $id,
						'post_title'   => 'UNPUB-' . $def[0],
						'post_content' => sprintf( $base['post_content'], $def[0] ),
					),
					$def[1]
				)
			);
		}

		foreach ( array( 3001 => 'About page', 3002 => 'Contact page' ) as $id => $title ) {
			$factory->post->create( array( 'import_id' => $id, 'post_type' => 'page', 'post_title' => $title, 'post_status' => 'publish', 'post_date' => '2025-01-01 10:00:00' ) );
		}
	}

	public function set_up() {
		parent::set_up();
		$this->reset_widget_counters();
		$GLOBALS['wp']->request = 'sample-page';
		$_GET                   = array();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $this->server );
	}

	public function tear_down() {
		$_GET = array();
		delete_option( 'afs_settings' );
		remove_all_filters( 'afs_query_args' );
		remove_all_filters( 'afs_template_file' );
		remove_all_filters( 'afs_enable_legacy_compat' );
		parent::tear_down();
	}

	/** The plugin counts widgets per request in private statics; tests act like fresh requests. */
	protected function reset_widget_counters() {
		foreach ( array( 'AFS_Renderer' => 'instances', 'AFS_Frontend' => 'rendered' ) as $class => $prop ) {
			$ref = new ReflectionProperty( $class, $prop );
			$ref->setAccessible( true );
			$ref->setValue( null, 0 );
		}
	}

	/** Dispatch a GET to the results route. */
	protected function rest( array $params = array() ) {
		$request = new WP_REST_Request( 'GET', '/afs/v1/results' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	/**
	 * Make output comparable across runs: one tag per line, and per-output sequence numbers for the
	 * process-wide unique id counter.
	 */
	protected function normalize( $html ) {
		$html = (string) $html;
		$seen = array();
		$html = preg_replace_callback(
			'/afs-summary-(\d+)/',
			static function ( $m ) use ( &$seen ) {
				if ( ! isset( $seen[ $m[1] ] ) ) {
					$seen[ $m[1] ] = count( $seen ) + 1;
				}
				return 'afs-summary-N' . $seen[ $m[1] ];
			},
			$html
		);
		$html = preg_replace( '/>\s+</', '><', $html );
		$html = preg_replace( '/></', ">\n<", $html );
		return trim( $html ) . "\n";
	}

	/** Compare with, or (UPDATE_SNAPSHOTS=1) write, tests/snapshots/<series>/<name>.txt. */
	protected function assertMatchesSnapshot( $name, $content ) {
		$series = getenv( 'AFS_SNAPSHOT_SERIES' ) ? getenv( 'AFS_SNAPSHOT_SERIES' ) : '2.0.0';
		$dir    = dirname( __DIR__ ) . '/snapshots/' . $series;
		$file   = $dir . '/' . $name . '.txt';
		if ( getenv( 'UPDATE_SNAPSHOTS' ) ) {
			if ( ! is_dir( dirname( $file ) ) ) {
				mkdir( dirname( $file ), 0777, true );
			}
			file_put_contents( $file, $content );
			$this->addToAssertionCount( 1 );
			return;
		}
		$this->assertFileExists( $file, 'Missing snapshot ' . $name . '. Generate it from the release tag with bin/generate-snapshots.sh.' );
		$this->assertSame( file_get_contents( $file ), $content, 'Output differs from snapshot ' . $series . '/' . $name );
	}
}
