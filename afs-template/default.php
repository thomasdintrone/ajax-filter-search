<?php
/**
 * Template for one result item.
 *
 * To customize, copy this file to  your-theme/afs-template/default.php.
 *
 * Available inside the loop (the global $post is already set up):
 *   $afs_terms    string[]  Term slugs this feed is limited to (empty = all).
 *   $afs_taxonomy string    Taxonomy used for the category tabs ('' = none).
 *
 * The wrapper <article> is printed for you.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

$afs_terms    = isset( $afs_terms ) ? (array) $afs_terms : array();
$afs_taxonomy = isset( $afs_taxonomy ) ? (string) $afs_taxonomy : '';

$afs_names = array();
if ( '' !== $afs_taxonomy ) {
	$afs_post_terms = get_the_terms( get_the_ID(), $afs_taxonomy );
	if ( $afs_post_terms && ! is_wp_error( $afs_post_terms ) ) {
		foreach ( $afs_post_terms as $afs_term ) {
			if ( 'uncategorized' === $afs_term->slug || ( $afs_terms && ! in_array( $afs_term->slug, $afs_terms, true ) ) ) {
				continue;
			}
			$afs_names[] = $afs_term->name;
		}
	}
}

$afs_summary    = get_the_selected_excerpt( 200 );
$afs_summary_id = wp_unique_id( 'afs-summary-' );
?>
<div class="prDateCol col-sm-2 afs-col-date">
	<div class="afs-PRDate"><?php echo esc_html( get_the_date() ); ?></div>
	<div class="afs-PRTimezone"><span class="afs-PRTime"><?php echo esc_html( get_the_time() ); ?></span> <span class="afs-timezone"><?php echo esc_html( get_the_time( 'T' ) ); ?></span></div>
</div>

<div class="col-sm-10 afs-col-title">
	<?php if ( $afs_names ) : ?>
		<p class="afs-terms"><?php echo esc_html( implode( ', ', $afs_names ) ); ?></p>
	<?php endif; ?>

	<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

	<?php if ( '' !== $afs_summary ) : ?>
		<div class="afs-PRTools">
			<button type="button" class="afs-summary-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $afs_summary_id ); ?>"><?php esc_html_e( 'Summary', 'ajax-filter-search' ); ?></button>
		</div>
		<div class="afs-PRSummary" id="<?php echo esc_attr( $afs_summary_id ); ?>" hidden>
			<p><?php echo esc_html( $afs_summary ); ?>&hellip;</p>
			<a class="afs-more" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Continue Reading', 'ajax-filter-search' ); ?></a>
		</div>
	<?php endif; ?>
</div>
