<?php
/**
 * A card shows a featured image, so cached results must refresh when the image itself changes, not only when the post's
 * featured-image setting does (audit finding 2): regenerated sizes, a replaced file, an edited alt or crop.
 */
class AttachmentCacheTest extends AFS_Test_Case {

	private $attachment;

	public function set_up() {
		parent::set_up();
		wp_cache_flush();
		$this->attachment = self::factory()->attachment->create_object( 'cover.jpg', 1002, array( 'post_mime_type' => 'image/jpeg' ) );
		set_post_thumbnail( 1002, $this->attachment );
		AFS_Cache::flush();
	}

	private function version_after( callable $change ) {
		AFS_Cache::flush();
		$before = AFS_Cache::version();
		$change();
		AFS_Cache::flush();
		return array( $before, AFS_Cache::version() );
	}

	/** @return array<string,array<int,string>> */
	public function image_meta_keys() {
		return array(
			'attachment metadata' => array( '_wp_attachment_metadata' ),
			'attached file'       => array( '_wp_attached_file' ),
			'featured image'      => array( '_thumbnail_id' ),
		);
	}

	/**
	 * @dataProvider image_meta_keys
	 */
	public function test_adding_updating_and_deleting_image_meta_changes_the_cache_version( $key ) {
		$id = 'featured image' === $key ? 1003 : $this->attachment;

		list( $before, $after ) = $this->version_after( static function () use ( $id, $key ) {
			add_post_meta( $id, $key, 'a' );
		} );
		$this->assertNotSame( $before, $after, 'adding ' . $key );

		list( $before, $after ) = $this->version_after( static function () use ( $id, $key ) {
			update_post_meta( $id, $key, 'b' );
		} );
		$this->assertNotSame( $before, $after, 'updating ' . $key );

		list( $before, $after ) = $this->version_after( static function () use ( $id, $key ) {
			delete_post_meta( $id, $key );
		} );
		$this->assertNotSame( $before, $after, 'deleting ' . $key );
	}

	public function test_regenerating_attachment_metadata_changes_the_cache_version() {
		list( $before, $after ) = $this->version_after( function () {
			wp_update_attachment_metadata( $this->attachment, array( 'width' => 800, 'height' => 500, 'file' => '2026/11/cover.jpg', 'sizes' => array() ) );
		} );
		$this->assertNotSame( $before, $after );
	}

	public function test_unrelated_meta_still_leaves_the_cache_alone() {
		list( $before, $after ) = $this->version_after( function () {
			update_post_meta( $this->attachment, 'some_other_key', 'x' );
			update_post_meta( 1002, '_edit_lock', time() );
		} );
		$this->assertSame( $before, $after );
	}

	public function test_a_conditional_request_made_before_an_image_change_is_not_answered_with_304() {
		$params = array( 'design' => 'editorial', 'show_image' => '1', 'afs_year' => 2024, 'afs_month' => 3, 'posts_per_page' => 5 );
		$first  = $this->rest( $params );
		$etag   = $first->get_headers()['ETag'];

		$again = new WP_REST_Request( 'GET', '/afs/v1/results' );
		foreach ( $params as $k => $v ) {
			$again->set_param( $k, $v );
		}
		$again->set_header( 'If-None-Match', $etag );
		$this->assertSame( 304, $this->server->dispatch( $again )->get_status(), 'Nothing changed yet: the validator still holds' );

		wp_update_attachment_metadata( $this->attachment, array( 'width' => 1200, 'height' => 750, 'file' => '2026/11/cover.jpg', 'sizes' => array() ) );
		AFS_Cache::flush();

		$after = new WP_REST_Request( 'GET', '/afs/v1/results' );
		foreach ( $params as $k => $v ) {
			$after->set_param( $k, $v );
		}
		$after->set_header( 'If-None-Match', $etag );
		$response = $this->server->dispatch( $after );
		$this->assertSame( 200, $response->get_status(), 'The old validator must no longer match once the image has changed' );
		$this->assertNotSame( $etag, $response->get_headers()['ETag'] );
	}

	public function test_the_attachment_hooks_stay_in_place() {
		foreach ( array( 'edit_attachment', 'delete_attachment' ) as $hook ) {
			$this->assertNotFalse( has_action( $hook, array( 'AFS_Cache', 'mark' ) ), $hook );
		}
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			$this->assertNotFalse( has_action( $hook, array( 'AFS_Cache', 'mark_image_meta' ) ), $hook );
		}
	}
}
