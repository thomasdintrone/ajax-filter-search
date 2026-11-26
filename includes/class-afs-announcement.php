<?php
/**
 * The "What's new" card on the plugin's own settings screen.
 *
 * It tells people who already use the plugin about the 2.3.0 designs, reassures them that nothing on their site changes
 * unless they choose a new design, and goes away for good when dismissed. It is shown only on the settings screen, only
 * to users who can manage the plugin, never to a fresh install (which already starts on the new look), and it makes no
 * external requests: the changelog link is a plain link that opens when clicked.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Announcement {

	/** The announcement's id. A later announcement uses a new id, so it can show once to people who dismissed this one. */
	const VERSION = '2.3.0';

	/** User meta: the id of the announcement this user dismissed. */
	const USER_META = 'afs_whatsnew_dismissed';

	/** Site option: set on a fresh install to the id of the announcement it never needs to see. */
	const OPTION = 'afs_whatsnew_site';

	/** The admin-post action that records a dismissal. */
	const ACTION = 'afs_dismiss_whatsnew';

	/** Release notes: the changelog on the plugin's WordPress.org page. */
	const CHANGELOG = 'https://wordpress.org/plugins/ajax-filter-search/#developers';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_dismiss' ) );
	}

	/** A site that installs the plugin fresh starts on the new look, so it has nothing to be told about. */
	public static function mark_fresh_install() {
		add_option( self::OPTION, self::VERSION );
	}

	/** Whether the reopen link was used ("What's new" in the screen header). Read only; changes nothing. */
	private static function reopened() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only: it only shows a card.
		return isset( $_GET['afs_whatsnew'] );
	}

	/**
	 * Whether the card shows for a user.
	 *
	 * @param int $user_id User ID (0 for the current user).
	 * @return bool
	 */
	public static function should_show( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id || ! user_can( $user_id, AFS_CAPABILITIES ) ) {
			return false;
		}
		if ( self::reopened() ) {
			return true;
		}
		if ( self::VERSION === get_option( self::OPTION ) || self::VERSION === get_user_meta( $user_id, self::USER_META, true ) ) {
			return false;
		}
		// A site whose own design is already one of the new ones has found them.
		return ! AFS_Design::has_own_markup( AFS_Design::resolve( '' ) );
	}

	/** Remember, for one user, that the announcement was dismissed. */
	public static function dismiss( $user_id ) {
		if ( $user_id && user_can( $user_id, AFS_CAPABILITIES ) ) {
			update_user_meta( (int) $user_id, self::USER_META, self::VERSION );
			return true;
		}
		return false;
	}

	/** The admin-post handler for the two dismiss buttons (with JavaScript they are sent in the background). */
	public static function handle_dismiss() {
		if ( ! current_user_can( AFS_CAPABILITIES ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ajax-filter-search' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		self::dismiss( get_current_user_id() );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
		if ( isset( $_POST['afs_ajax'] ) ) {
			wp_send_json_success();
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=' . AFS_MENU_SLUG ) );
		exit;
	}

	/** URL of the settings screen with the card shown again. */
	public static function reopen_url() {
		return add_query_arg( 'afs_whatsnew', '1', admin_url( 'options-general.php?page=' . AFS_MENU_SLUG ) ) . '#afs-news';
	}

	/** The card, or nothing. Echoes escaped markup. */
	public static function render() {
		if ( ! self::should_show() ) {
			return;
		}
		$img = AFS_PLUGIN_URL . '/admin/images/';
		?>
		<div id="afs-news-status" class="screen-reader-text" role="status" aria-live="polite"></div>
		<section id="afs-news" class="afs-news" role="region" aria-labelledby="afs-news-title">
			<form id="afs-news-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
			</form>
			<button type="submit" form="afs-news-form" class="afs-news__x" aria-label="<?php esc_attr_e( 'Dismiss this announcement', 'ajax-filter-search' ); ?>">
				<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 3l10 10M13 3L3 13"/></svg>
			</button>

			<div class="afs-news__text">
				<p class="afs-news__pill"><?php echo esc_html( sprintf( /* translators: %s: version number. */ __( 'New in %s', 'ajax-filter-search' ), self::VERSION ) ); ?></p>
				<h2 id="afs-news-title"><?php esc_html_e( 'Meet the next generation of Ajax Filter Search.', 'ajax-filter-search' ); ?></h2>
				<p class="afs-news__copy"><?php esc_html_e( 'Discover a more beautiful way to showcase your content. New editorial layouts, compact resource libraries, featured images, customizable excerpts and intuitive design controls.', 'ajax-filter-search' ); ?></p>
				<p class="afs-news__keep">
					<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M10 2l6 2.2v5.1c0 4-2.5 7-6 8.7-3.5-1.7-6-4.7-6-8.7V4.2L10 2z"/><path d="M7.2 10l2 2 3.6-4"/></svg>
					<span>
						<strong><?php esc_html_e( 'Your existing feeds are unchanged.', 'ajax-filter-search' ); ?></strong>
						<?php esc_html_e( 'Layouts, settings, shortcodes and blocks look and work exactly as before. Nothing changes unless you choose a new design.', 'ajax-filter-search' ); ?>
					</span>
				</p>
				<div class="afs-news__actions">
					<a class="afs-news__btn" href="#afs-sec-appearance" data-afs-explore><?php esc_html_e( 'Explore the new designs', 'ajax-filter-search' ); ?></a>
					<a class="afs-news__link" href="<?php echo esc_url( self::CHANGELOG ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See what\'s new', 'ajax-filter-search' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ajax-filter-search' ); ?></span></a>
					<button type="submit" form="afs-news-form" class="afs-news__later"><?php esc_html_e( 'Maybe later', 'ajax-filter-search' ); ?></button>
				</div>
			</div>

			<div class="afs-news__shots" aria-hidden="true">
				<div class="a"><img src="<?php echo esc_url( $img . 'whatsnew-editorial.webp' ); ?>" alt="" width="760" height="371" loading="lazy" decoding="async"></div>
				<div class="b"><img src="<?php echo esc_url( $img . 'whatsnew-library.webp' ); ?>" alt="" width="760" height="331" loading="lazy" decoding="async"></div>
			</div>
		</section>
		<?php
	}
}
