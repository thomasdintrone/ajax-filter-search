<?php 
/*******************************************************************
* PAGINATION
*******************************************************************/
if (!function_exists('afs_page_navi')) {
	// Numeric Pagination
	function afs_page_navi( $args = array() ) {
	
		$defaults = array(
			'range'           => 10,
			'offset'			  => 0,
			'custom_query'    => FALSE,
			'previous_string' => __( '<i class="fa fa-angle-double-left"></i>', 'text-domain' ),
			'next_string'     => __( '<i class="fa fa-angle-double-right"></i>', 'text-domain' ),
			'before_output'   => '<div class="post-nav"><ul class="pagination pull-right">',
			'after_output'    => '</ul></div>',
			'echo'			  => true
		);    
	
		$args = wp_parse_args( 
			$args, 
			apply_filters( 'wp_bootstrap_pagination_defaults', $defaults )
		);
	
		$args['range'] = (int) $args['range'] - 1;
		if ( !$args['custom_query'] )
			$args['custom_query'] = @$GLOBALS['wp_query'];
		$count = (int) $args['custom_query']->max_num_pages;
		if($args['offset'] == 0) {
			$page  = intval( get_query_var( 'paged' ) );
		} else {
			$page = $args['offset'];
		}
	
		$ceil  = ceil( $args['range'] / 2 );
	
		if ( $count <= 1 )
			return FALSE;
	
		if ( !$page )
			$page = 1;
	
		if ( $count > $args['range'] ) {
			if ( $page <= $args['range'] ) {
				$min = 1;
				$max = $args['range'] + 1;
			} elseif ( $page >= ($count - $ceil) ) {
				$min = $count - $args['range'];
				$max = $count;
			} elseif ( $page >= $args['range'] && $page < ($count - $ceil) ) {
				$min = $page - $ceil;
				$max = $page + $ceil;
			}
		} else {
			$min = 1;
			$max = $count;
		}
	
		$echo = '';
		$previous = intval($page) - 1;
		//$previous = esc_attr( get_pagenum_link($previous) );
		
		$firstpage = esc_attr( get_pagenum_link(1) );
		if ( $firstpage && (1 != $page) )
			//$echo .= '<li><a class="btn-default btn" href="' . $firstpage . '">' . __( 'First', 'text-domain' ) . '</a></li>';
		if ( $previous && (1 != $page) )
			$echo .= '<li><a class="btn-default btn" href="' . $previous . '" title="' . __( 'previous', 'text-domain') . '">' . $args['previous_string'] . '</a></li>';    
	
		if ( !empty($min) && !empty($max) ) {
			for( $i = $min; $i <= $max; $i++ ) {
				if ($page == $i) {
					$echo .= '<li><a class="btn btn-primary" href="'.get_pagenum_link($i).'">' . str_pad( (int)$i, 1, '0', STR_PAD_LEFT ) . '</a></li>';
				} else {
					$echo .= sprintf( '<li><a class="btn-default btn" href="%s">%2d</a></li>', esc_attr( get_pagenum_link($i) ), $i );
				}
			}
		}
	
		$next = intval($page) + 1;
		//$next = esc_attr( get_pagenum_link($next) );
	
		if ($next && ($count != $page) )
			$echo .= '<li><a class="btn-default btn" href="' . $next . '" title="' . __( 'next', 'text-domain') . '">' . $args['next_string'] . '</a></li>';
	
		$lastpage = esc_attr( get_pagenum_link($count) );
		if ( $lastpage ) {
			//$echo .= '<li class="next"><a href="' . $lastpage . '">' . __( 'Last', 'text-domain' ) . '</a></li>';
		}
	
		if ( isset($echo) ) {
			if($args['echo'] == true) {
				echo $args['before_output'] . $echo . $args['after_output'];
			} else {
				return $args['before_output'] . $echo . $args['after_output'];
			}
		}
	}
}


/*******************************************************************
* LIMIT EXCERPT LENGTH BY CHARACTERS
********************************************************************/
if (!function_exists('get_the_selected_excerpt')) {
	/* Limit Excerpt length by characters */
	function get_the_selected_excerpt($length=40){
		$excerpt = get_the_content();
		$excerpt = preg_replace(" (\[.*?\])",'',$excerpt);
		$excerpt = strip_shortcodes($excerpt);
		$excerpt = strip_tags($excerpt);
		$excerpt = substr($excerpt, 0, $length);
		$excerpt = substr($excerpt, 0, strripos($excerpt, " "));
		$excerpt = trim(preg_replace( '/\s+/', ' ', $excerpt));
		//$excerpt = $excerpt.'... <a href="'.$permalink.'">more</a>';
		return $excerpt;
	}
}


/*******************************************************************
* RETURN TEMPLATE
********************************************************************/
if (!function_exists('return_template')) {
	function return_template($template='default.php'){
		ob_start();
		if (file_exists(STYLESHEETPATH.'/afs-template/'.$template)) {
			// yep, load the page template
			include( STYLESHEETPATH.'/afs-template/'.$template );
		} else {
			// nope, load the set default
			include( WP_PLUGIN_DIR.'/'.AFS_MENU_SLUG.'/afs-template/'.$template );
			
		}
		return ob_get_clean();
	}
}


/*******************************************************************
* AJAX FUN STUFF
********************************************************************/
if (!function_exists('myplugin_ajaxurl')) {
	add_action('wp_head', 'myplugin_ajaxurl');
	
	function myplugin_ajaxurl() {
	
	   echo '<script type="text/javascript">
			   var ajaxurl = "' . admin_url('admin-ajax.php') . '";
			   var scriptpath = "' . site_url() . '";
			 </script>';
	}
}

if (!function_exists('my_action_callback')) {
	// Ajax call to sort PRs
	function my_action_callback() {  
	
		// Get the values:
		$post_type		= AFSAdmin::afs_retrieve('_general_post_type');
		$posts_per_page	= AFSAdmin::afs_retrieve('_general_posts_per_page');
		$filter_type 	= $_POST['filingType'];
		$filter_by 		= $_POST['filterBy'];
		$filter_months 	= $_POST['filterMonths'];
		$filter_years 	= $_POST['filterYears'];
		$filter_withPDF = $_POST['withPDF'];
	
		$offset  		= $_POST['page'];
		
		echo do_shortcode('[afs_feed post_type="'.$post_type.'" posts_per_page="'.$posts_per_page.'" offset="'.$offset.'" filter_type="'.$filter_type.'" filter_by="'.$filter_by.'" filter_months="'.$filter_months.'" filter_years="'.$filter_years.'" filter_withPDF="'.$filter_withPDF.'"]');
	
		exit;
	
	}
	
	add_action('wp_ajax_my_action', 'my_action_callback');
	add_action('wp_ajax_nopriv_my_action', 'my_action_callback');
}

/*******************************************************************
* AJAX IN ADMIN
********************************************************************/
if (!function_exists('get_selected_taxonomy')) {
	// Ajax call to get taxnomy values
	function get_selected_taxonomy() {  
	
		// Get the values:
		$cur_post_tax	= AFSAdmin::afs_retrieve('_general_post_taxonomy');
		$post_type 		= $_POST['option'];
		
		$taxonomy_objects = get_object_taxonomies($post_type, 'objects');
		
		if($taxonomy_objects) {
			foreach($taxonomy_objects as $tax) {
				$sel = '';
				if($tax->name == 'post_format') {
					continue;
				} else {
					if($tax->name == $cur_post_tax) { $sel = 'selected="selected"'; }
					echo '<option value="'.$tax->name.'" '.$sel.'>'.$tax->label.'</option>';
				}
			}	
		} else {
			echo '<option value="none" '.$sel.'>None</option>';
		}
	
		exit;
	
	}
	
	add_action('wp_ajax_get_selected', 'get_selected_taxonomy');
	add_action('wp_ajax_nopriv_get_selected', 'get_selected_taxonomy');
}

/*add_action( 'admin_footer', 'my_action_javascript' ); // Write our JS below here

function my_action_javascript() { ?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		
		$('#afs-form input#submit').on({
			click: function(){
				
				//return false;
				
			}
		});
		
		var data = {
			'action': 'my_action',
			'whatever': 1234
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			console.log('Got this from the server: ' + response);
		});
	});
	</script> <?php
}*/
?>