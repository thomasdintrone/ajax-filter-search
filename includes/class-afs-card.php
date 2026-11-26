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
		$own   = AFS_Design::has_own_markup( $design );
		$bool  = static function ( $key, $default ) use ( $raw ) {
			return isset( $raw[ $key ] ) && '' !== $raw[ $key ] ? wp_validate_boolean( $raw[ $key ] ) : $default;
		};
		$saved = static function ( $key ) {
			return AFSAdmin::afs_retrieve( '_cards_' . $key );
		};

		$ratio = isset( $raw['image_ratio'] ) ? self::clean_ratio( $raw['image_ratio'] ) : '';
		if ( '' === $ratio ) {
			$ratio = self::clean_ratio( $saved( 'image_ratio' ) );
		}
		$ratio = '' === $ratio ? '16:10' : $ratio;

		$columns = isset( $raw['columns'] ) ? absint( $raw['columns'] ) : 0;
		if ( ! $columns ) {
			$columns = absint( $saved( 'columns' ) );
		}
		$columns = min( 4, max( 1, $columns ? $columns : 3 ) );

		$length = isset( $raw['excerpt_length'] ) ? absint( $raw['excerpt_length'] ) : 0;
		if ( ! $length ) {
			$length = absint( $saved( 'excerpt_length' ) );
		}
		$length = min( 80, max( 10, $length ? $length : 25 ) );

		$show_date = $bool( 'show_date', 1 === (int) $saved( 'show_date' ) );

		return array(
			// Images belong to the designs with their own markup; Classic and Modern never show them.
			'show_image'     => $bool( 'show_image', $own && 1 === (int) $saved( 'show_image' ) ),
			'image_ratio'    => $ratio,
			'columns'        => $columns,
			'show_excerpt'   => $bool( 'show_excerpt', 1 === (int) $saved( 'show_excerpt' ) ),
			'excerpt_length' => $length,
			'show_date'      => $show_date,
			// The time is part of the date: with the date hidden there is nothing to put it next to.
			'show_time'      => $show_date && $bool( 'show_time', 1 === (int) $saved( 'show_time' ) ),
			'show_category'  => $bool( 'show_category', 1 === (int) $saved( 'show_category' ) ),
		);
	}

	/**
	 * A ratio as a key of self::RATIOS ("16:10"), or '' when it is not one. A slash or dash is accepted for the colon.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_ratio( $value ) {
		$value = str_replace( array( '/', ' ', '-' ), array( ':', '', ':' ), sanitize_text_field( (string) $value ) );
		return isset( self::RATIOS[ $value ] ) ? $value : '';
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

	/**
	 * Whether a post is password protected, read from the post itself.
	 *
	 * This is deliberately not post_password_required(): that answers "does this visitor still need to enter the password?"
	 * and changes with the visitor's cookie. What the plugin shows (and what its public, cacheable responses contain) must
	 * not depend on who is asking, so a protected post never exposes an image or an excerpt, even to someone who has entered
	 * the password. Its title stays listed, as in any WordPress archive.
	 *
	 * @param mixed $post Post, ID or null for the current post.
	 * @return bool
	 */
	public static function is_password_locked( $post = null ) {
		$post = get_post( $post );
		return $post instanceof WP_Post && '' !== (string) $post->post_password;
	}

	/** True when the post's image can be shown: a featured image exists and the post is not password protected. */
	public static function has_image( $post = null ) {
		$post = get_post( $post );
		return $post && ! self::is_password_locked( $post ) && has_post_thumbnail( $post );
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
	 * Classes for a result item. The row design drops its thumbnail column only when images are turned off; a post with no
	 * image still gets a small tile, so every title in the list starts at the same place.
	 *
	 * @param string $design  Design slug.
	 * @param array  $options Template options.
	 * @return string[]
	 */
	public static function item_classes( $design, $options ) {
		$classes = array( 'afs-item', AFS_Design::item_class( $design ) );
		if ( AFS_Design::LIBRARY === $design && empty( $options['show_image'] ) ) {
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
		return '<span class="afs-img afs-img--none" aria-hidden="true"><span class="afs-img__mono">' . esc_html( $letter ) . '</span></span>';
	}

	/**
	 * Plain-text excerpt of the current post, cut by words. Empty for a password-protected post.
	 *
	 * @param int $words Maximum number of words.
	 * @return string Plain text; the template escapes it.
	 */
	public static function excerpt( $words = 25 ) {
		$post = get_post();
		if ( ! $post || self::is_password_locked( $post ) ) {
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
