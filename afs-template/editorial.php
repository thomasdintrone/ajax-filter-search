<?php
/**
 * Template for one result item in the Editorial Grid design: an image card with a category badge, title, excerpt and date.
 *
 * To customize, copy this file to  your-theme/afs-template/editorial.php.
 *
 * Available inside the loop (the global $post is already set up):
 *   $afs_terms    string[]  Term slugs this feed is limited to (empty = all).
 *   $afs_taxonomy string    Taxonomy used for the category tabs ('' = none).
 *   $afs_options  array     Card options: show_image, image_ratio, show_excerpt, excerpt_length, show_date, show_time,
 *                           show_category, columns.
 *
 * The wrapper <article> is printed for you. Image and excerpt are never shown for a password-protected post.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

$afs_terms    = isset( $afs_terms ) ? (array) $afs_terms : array();
$afs_taxonomy = isset( $afs_taxonomy ) ? (string) $afs_taxonomy : '';
$afs_options  = isset( $afs_options ) ? (array) $afs_options : array();

$afs_names   = ! empty( $afs_options['show_category'] ) ? AFS_Card::term_names( $afs_taxonomy, $afs_terms ) : array();
$afs_excerpt = ! empty( $afs_options['show_excerpt'] ) ? AFS_Card::excerpt( isset( $afs_options['excerpt_length'] ) ? $afs_options['excerpt_length'] : 25 ) : '';
$afs_image   = ! empty( $afs_options['show_image'] ) ? AFS_Card::image( 'medium_large', true, $afs_names ? $afs_names[0] : '' ) : '';
?>
<?php echo $afs_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AFS_Card::image(). ?>
<div class="afs-card__body">
	<?php if ( $afs_names ) : ?>
		<span class="afs-badge"><?php echo esc_html( $afs_names[0] ); ?></span>
	<?php endif; ?>

	<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

	<?php if ( '' !== $afs_excerpt ) : ?>
		<p class="afs-excerpt"><?php echo esc_html( $afs_excerpt ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $afs_options['show_date'] ) || ! empty( $afs_options['show_time'] ) ) : ?>
		<p class="afs-meta">
			<?php if ( ! empty( $afs_options['show_date'] ) ) : ?>
				<time class="afs-date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php endif; ?>
			<?php if ( ! empty( $afs_options['show_time'] ) ) : ?>
				<span class="afs-time"><?php echo esc_html( get_the_time() ); ?></span>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
