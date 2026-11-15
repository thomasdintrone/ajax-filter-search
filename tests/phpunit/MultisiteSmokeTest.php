<?php
/**
 * Only meaningful when the suite runs as a network (AFS_MULTISITE=1 bin/test.sh); skipped otherwise.
 */
class MultisiteSmokeTest extends AFS_Test_Case {

	public function test_a_second_site_lists_only_its_own_published_posts() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run with AFS_MULTISITE=1.' );
		}

		$blog = self::factory()->blog->create();
		switch_to_blog( $blog );
		self::factory()->post->create( array( 'post_title' => 'Second site only', 'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_title' => 'Second site draft', 'post_status' => 'draft' ) );

		$config = AFS_Renderer::normalize_config( array( 'posts_per_page' => 20 ) );
		$state  = AFS_Renderer::normalize_state( array(), $config );
		$titles = wp_list_pluck( AFS_Query::run( $config, $state )->posts, 'post_title' );
		restore_current_blog();

		$this->assertContains( 'Second site only', $titles );
		$this->assertNotContains( 'Second site draft', $titles );
		$this->assertNotContains( 'Alpha report one', $titles, 'The first site\'s posts must not leak into the second site' );
	}
}
