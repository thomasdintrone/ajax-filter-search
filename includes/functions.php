<?php
/**
 * Helper functions. The two public helpers below are used by theme template
 * overrides (afs-template/default.php) in 1.x, so their names and behavior are kept.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'get_the_selected_excerpt' ) ) {
	/**
	 * Return a plain-text teaser of the current post, cut at a word boundary.
	 *
	 * @param int $length Maximum number of characters.
	 * @return string
	 */
	function get_the_selected_excerpt( $length = 40 ) {
		$post = get_post();
		if ( ! $post || post_password_required( $post ) ) {
			return '';
		}

		$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$text = strip_shortcodes( $text );
		$text = preg_replace( '/\[[^\]]*\]/', '', $text );
		$text = wp_strip_all_tags( $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );

		$length = max( 1, (int) $length );
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$text  = mb_substr( $text, 0, $length );
		$space = mb_strrpos( $text, ' ' );
		if ( false !== $space ) {
			$text = mb_substr( $text, 0, $space );
		}

		return trim( $text );
	}
}

if ( ! function_exists( 'return_template' ) ) {
	/**
	 * Render one result item through a template, preferring the theme's copy.
	 *
	 * Override by copying afs-template/default.php into
	 * your-theme/afs-template/default.php (a child theme is checked first).
	 *
	 * @param string $template Template file name.
	 * @param array  $terms    Term slugs the feed is limited to (may be empty).
	 * @param string $taxonomy Taxonomy the feed's tabs use ('' for none).
	 * @return string
	 */
	function return_template( $template = 'default.php', $terms = array(), $taxonomy = '' ) {
		$template     = basename( $template );
		$afs_terms    = (array) $terms;
		$afs_taxonomy = (string) $taxonomy;

		$file = AFS_PLUGIN_PATH . 'afs-template/' . $template;
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $dir ) {
			if ( file_exists( $dir . '/afs-template/' . $template ) ) {
				$file = $dir . '/afs-template/' . $template;
				break;
			}
		}

		/**
		 * Filters the template file used for each result item.
		 *
		 * @param string $file     Absolute path.
		 * @param string $template Requested template name.
		 */
		$file = apply_filters( 'afs_template_file', $file, $template );

		ob_start();
		include $file;
		return ob_get_clean();
	}
}
