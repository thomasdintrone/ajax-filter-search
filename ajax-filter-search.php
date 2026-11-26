<?php
/**
 * Plugin Name:       Ajax Filter Search: Filter Posts by Category, Date & Keyword
 * Plugin URI:        https://streetcarpro.com
 * Description:       Let visitors filter and search posts or custom post types by category, month, year and keyword, with no page reload. Shortcode and block included.
 * Version:           2.2.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Thomas Dintrone
 * Author URI:        https://streetcarpro.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ajax-filter-search
 * Domain Path:       /languages
 * Copyright:         Drive Media, Inc.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

define( 'AFS_VERSION', '2.2.2' );
define( 'AFS_URL', 'https://streetcarpro.com' );

// Options and slugs. Kept identical to 1.x so saved settings carry over.
define( 'AFS_SUB', 'afs' );
define( 'AFS_SETTINGS', 'afs_settings' );
define( 'AFS_PAGE_TITLE', 'Ajax Filter Search' );
define( 'AFS_MENU_TAB_TITLE', 'Ajax Filter Search' );
define( 'AFS_CAPABILITIES', 'manage_options' );
define( 'AFS_MENU_SLUG', 'ajax-filter-search' );

define( 'AFS_PLUGIN_FILE', plugin_basename( __FILE__ ) );
define( 'AFS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AFS_PLUGIN_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );

require_once AFS_PLUGIN_PATH . 'includes/functions.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-settings.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-design.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-registry.php';
require_once AFS_PLUGIN_PATH . 'includes/api.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-query.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-cache.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-migration.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-renderer.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-rest.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-feed.php';
require_once AFS_PLUGIN_PATH . 'includes/class-afs-frontend.php';
require_once AFS_PLUGIN_PATH . 'includes/compat.php';

register_activation_hook( __FILE__, array( 'AFS_Design', 'activate' ) );

AFSAdmin::init();
AFS_Frontend::init();
AFS_REST::init();
AFS_Feed::init();
AFS_Compat::init();
AFS_Registry::init();
AFS_Cache::init();
AFS_Migration::init();
