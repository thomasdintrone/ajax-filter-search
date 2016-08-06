<?php 
/**
 * @package ajax-filter-search
 * @version 1.0.3
 */
/*

Plugin Name: Ajax Filter Search
Plugin URI: http://streetcarpro.com
Description: Displays posts or custom post types in a friendly, filterable format using ajax.
Text Domain: ajax-filter-search
Author: Thomas Dintrone
Twitter: @streetcarpro
Author URI: http://streetcarpro.com
Version: 1.0.3
License: GPL
Copyright: Drive Media, Inc.

*/

/*******************************************************************
* DEFINE CONSTANTS
********************************************************************/
define('AFS_VERSION', '1.0.3');
define('AFS_RELEASE', 'July 20, 2016');
define('AFS_URL', 'http://streetcarpro.com');


/*******************************************************************
* CORE & INITIALIZER
********************************************************************/
if( !class_exists('AFSSettings') ) :
	
	class AFSSettings {	
   			
		function __construct(){	   
			
			/* GLOBAL VARIABLES */
			define('AFS_SUB', 'afs');		
			define('AFS_SETTINGS', 'afs_settings');	
			
			
			/* OPTIONS VARIABLES */
			define('AFS_PAGE_TITLE', 'Ajax Filter Search');
			define('AFS_MENU_TAB_TITLE', 'Ajax Filter Search');
			define('AFS_CAPABILITIES', 'manage_options'); // (https://codex.wordpress.org/Roles_and_Capabilities#Capabilities)
			define('AFS_MENU_SLUG', 'ajax-filter-search');
			define('AFS_CALLBACK', 'afs_options_page'); // The name of the function that displays the HTML
			
			define('AFS_PLUGIN_PATH', plugin_dir_path(__FILE__));
			define('AFS_PLUGIN_FILE', plugin_basename( __FILE__ ));
   			define('AFS_PLUGIN_URL', plugins_url('', __FILE__));
   			define('AFS_PLUGIN_ADMIN_URL', plugins_url('admin/', __FILE__));
			
			/* LOAD NECESSARY FILES */
			// Admin Files
			require_once (dirname(__FILE__).'/admin/settings.php');
			
			// Core Files
			require_once (dirname(__FILE__).'/core/includes/functions.php');
			require_once (dirname(__FILE__).'/core/includes/shortcodes.php');			
						
		}	
		
		
	}
	
	// TURN ON THE SETTINGS
	function AFSinit(){
			
		global $afs;   
		
		if(!isset($afs)) { $afs = new AFSSettings(); }
		   
		return $afs;
	}  
   
   AFSinit();

endif;

?>