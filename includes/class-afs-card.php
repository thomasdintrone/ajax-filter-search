<?php
/**
 * What a result card or row shows: image, excerpt and metadata, plus the validated options that control them.
 *
 * Used by the templates of the designs that have their own item markup (editorial.php, library.php). Everything here
 * is output-safe or returns plain text for the template to escape, and nothing is shown for a password-protected post.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Card {

	/** Aspect ratios an image can have: key => CSS value. The CSS value is the only thing that reaches a stylesheet. */
	const RATIOS = array(
		'16:10' => '16 / 10',
		'3:2'   => '3 / 2',
		'4:3'   => '4 / 3',
		'1:1'   => '1 / 1',
		'16:9'  => '16 / 9',
	);

	/** Option keys that are on or off. */
	const BOOLS = array( 'show_image', 'show_excerpt', 'show_date', 'show_time', 'show_category' );

	/**
	 * Validate the card options in raw shortcode, block or REST attributes. Unknown or out-of-range values fall back to
	 * the default, so nothing from a request ever reaches the output unchecked.
	 *
	 * @param array  $raw    Raw attributes (empty values already removed).
	 * @param string $design Resolved design slug.
	 * @return array
	 */
	public static function normalize_options( $raw, $design ) {
		$bool = static function ( $key, $default ) use ( $raw ) {
			return isset( $raw[ $key ] ) && '' !== $raw[ $key ] ? wp_validate_boolean( $raw[ $key ] ) : $default;
		};

		$ratio = isset( $raw['image_ratio'] ) ? str_replace( array( '/', ' ', '-' ), array( ':', '', ':' ), sanitize_text_field( $raw['image_ratio'] ) ) : '';
		if ( ! isset( self::RATIOS[ $ratio ] ) ) {
			$ratio = '16:10';
		}

		$columns = isset( $raw['columns'] ) ? absint( $raw['columns'] ) : 3;
		$columns = min( 4, max( 1, $columns ? $columns : 3 ) );

		$length = isset( $raw['excerpt_length'] ) ? absint( $raw['excerpt_length'] ) : 25;
		$length = min( 80, max( 10, $length ? $length : 25 ) );

		return array(
			'show_image'     => $bool( 'show_image', AFS_Design::has_own_markup( $design ) ),
			'image_ratio'    => $ratio,
			'columns'        => $columns,
			'show_excerpt'   => $bool( 'show_excerpt', true ),
			'excerpt_length' => $length,
			'show_date'      => $bool( 'show_date', true ),
			'show_time'      => $bool( 'show_time', false ),
			'show_category'  => $bool( 'show_category', true ),
		);
	}

	/**
	 * The options as the script sends them to the REST route (booleans as 1 and 0).
	 *
	 * @param array $config Normalized config.
	 * @return array
	 */
	public static function client_options( $config ) {
		$out = array();
		foreach ( self::BOOLS as $key ) {
			$out[ $key ] = $config[ $key ] ? '1' : '0';
		}
		$out['image_ratio']    = $config['image_ratio'];
		$out['columns']        = $config['columns'];
		$out['excerpt_length'] = $config['excerpt_length'];
		return $out;
	}

	/**
	 * Per-feed CSS custom properties for the card grid, for the root element's style attribute. Built only from the
	 * validated column count and ratio, so nothing from a request reaches a stylesheet.
	 *
	 * @param array $config Normalized config.
	 * @return string
	 */
	public static function css_vars( $config ) {
		$ratio = isset( self::RATIOS[ $config['image_ratio'] ] ) ? self::RATIOS[ $config['image_ratio'] ] : self::RATIOS['16:10'];
		return '--afs-cols:' . (int) $config['columns'] . ';--afs-ratio:' . $ratio . ';';
	}

	/**
	 * The options a template needs.
	 *
	 * @param array $config Normalized config.
	 * @return array
	 */
	public static function template_options( $config ) {
		$keys = array_merge( self::BOOLS, array( 'image_ratio', 'columns', 'excerpt_length' ) );
		return array_intersect_key( $config, array_flip( $keys ) );
	}

	/** True when the post's image can be shown: a featured image exists and the post is not password protected. */
	public static function has_image( $post = null ) {
		$post = get_post( $post );
		return $post && ! post_password_required( $post ) && has_post_thumbnail( $post );
	}

	/**
	 * Prime the featured-image caches for a query's posts in one go, so cards do not run one query each.
	 *
	 * @param WP_Query $query The feed query.
	 */
	public static function prime( $query ) {
		if ( function_exists( 'update_post_thumbnail_cache' ) ) {
			update_post_thumbnail_cache( $query );
		}
	}

	/**
	 * Classes for a result item. The row design drops its thumbnail column for a post with no image.
	 *
	 * @param string $design  Design slug.
	 * @param array  $options Template options.
	 * @param mixed  $post    Post, ID or null for the current post.
	 * @return string[]
	 */
	public static function item_classes( $design, $options, $post = null ) {
		$classes = array( 'afs-item', AFS_Design::item_class( $design ) );
		if ( AFS_Design::LIBRARY === $design && ( empty( $options['show_image'] ) || ! self::has_image( $post ) ) ) {
			$classes[] = 'afs-row--nothumb';
		}
		return $classes;
	}

	/**
	 * The image block of a card, or a tinted tile with the category's first letter when the post has no image.
	 *
	 * The image link is hidden from assistive technology and the tab order (the title is the real link) and the image has
	 * an empty alt so it is not read twice. A password-protected post never shows its image.
	 *
	 * @param string $size     Image size name.
	 * @param bool   $tile     Show the placeholder tile when there is no image.
	 * @param string $fallback Text the placeholder takes its first letter from.
	 * @return string HTML (escaped), or '' when nothing should be shown.
	 */
	public static function image( $size = 'medium_large', $tile = true, $fallback = '' ) {
		$post = get_post();
		if ( ! $post ) {
			return '';
		}

		if ( self::has_image( $post ) ) {
			$img = get_the_post_thumbnail(
				$post,
				$size,
				array(
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'class'    => 'afs-img__el',
				)
			);
			if ( '' !== $img ) {
				return '<a class="afs-img" href="' . esc_url( get_permalink( $post ) ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
			}
		}

		if ( ! $tile ) {
			return '';
		}
		$text   = '' !== $fallback ? $fallback : get_the_title( $post );
		$letter = '' !== $text ? mb_strtoupper( mb_substr( wp_strip_all_tags( $text ), 0, 1 ) ) : '';
		return '<span class="afs-img afs-img--none" aria-hidden="true">' . esc_html( $letter ) . '</span>';
	}

	/**
	 * Plain-text excerpt of the current post, cut by words. Empty for a password-protected post.
	 *
	 * @param int $words Maximum number of words.
	 * @return string Plain text; the template escapes it.
	 */
	public static function excerpt( $words = 25 ) {
		$post = get_post();
		if ( ! $post || post_password_required( $post ) ) {
			return '';
		}
		$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$text = strip_shortcodes( $text );
		$text = preg_replace( '/\[[^\]]*\]/', '', $text );
		$text = wp_strip_all_tags( $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		return wp_trim_words( $text, max( 1, (int) $words ), '…' );
	}

	/**
	 * Names of the feed's taxonomy terms on the current post, with the same exclusions as the default template
	 * ("uncategorized", and terms outside the feed's own term list).
	 *
	 * @param string   $taxonomy Taxonomy the feed's tabs use ('' for none).
	 * @param string[] $terms    Term slugs the feed is limited to.
	 * @return string[]
	 */
	public static function term_names( $taxonomy, $terms = array() ) {
		$names = array();
		if ( '' === $taxonomy ) {
			return $names;
		}
		$post_terms = get_the_terms( get_the_ID(), $taxonomy );
		if ( $post_terms && ! is_wp_error( $post_terms ) ) {
			foreach ( $post_terms as $term ) {
				if ( 'uncategorized' === $term->slug || ( $terms && ! in_array( $term->slug, $terms, true ) ) ) {
					continue;
				}
				$names[] = $term->name;
			}
		}
		return $names;
	}
}
