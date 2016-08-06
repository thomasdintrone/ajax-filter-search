<?php 
/*******************************************************************
* BUILD THE SHORTCODE
********************************************************************/
// [ajax_filter_search]
function ajax_filter_search($atts, $content = null) {
    extract(shortcode_atts(array(
		'post_type'				=> AFSAdmin::afs_retrieve('_general_post_type'),
		'post_tax' 				=> AFSAdmin::afs_retrieve('_general_post_taxonomy'),
		'posts_per_page'		=> AFSAdmin::afs_retrieve('_general_posts_per_page'),
		'filter_type'			=> '',
		'filter_by' 				=> '',
		'filter_months' 			=> '',
		'filter_years' 			=> '',
		'filter_withPDF' 		=> '',
		'offset' 				=> 0,
    ), $atts));
	
	// Build String to attach to [afs_feed] to allow for custom building
	$afs_args = '';
	if($post_type == '') { } else { $afs_args .=' post_type="'.$post_type.'" '; }
	if($posts_per_page == '') { } else { $afs_args .=' posts_per_page="'.$posts_per_page.'" '; }
	if($filter_by == '') { } else { $afs_args .=' filter_by="'.$filter_by.'" '; }
	if($filter_months == '') { } else { $afs_args .=' filter_months="'.$filter_months.'" '; }
	if($filter_years == '') { } else { $afs_args .=' filter_years="'.$filter_years.'" '; }
	if($filter_withPDF == '') { } else { $afs_args .=' filter_withPDF="'.$filter_withPDF.'" '; }
	if($offset == '') { } else { $afs_args .=' offset="'.$offset.'" '; }
		
	if($filter_type == '') { 
		$all='all'; 
		$filter_type_count = 999;
	} else { 
		$afs_args .=' filter_type="'.$filter_type.'" '; 
		$all=$filter_type;
		$filter_type_array = explode(',',$filter_type);
		$filter_type_count = count($filter_type_array);
	}
		
	$text = '';
	$i = 1;
	
	/****************************
	Template Header
	****************************/
	$text .= '<div id="afs-wrapper">';
	$text .= '	<div class="press-releases">';
	$text .= '		<form id="newsForm">';
	$text .= '			<div class="row">';
	
	
	$text .= '				<div class="col-xs-1TableRowItem2">';
	
	/****************************
	Top Tabs
	****************************/
	
	if(AFSAdmin::afs_retrieve('_general_show_filters') == 1 && $filter_type_count > 1) { 
	$taxonomy = AFSAdmin::afs_retrieve('_general_post_taxonomy');
		if($taxonomy == 'none' || $taxonomy == '') {
		} else {
                
			$text .= '					<div class="afs-Tabs col-xs-12">';
			$text .= '						<ul class="hidden-xs afs-CommonTabs">';
			$text .= '							<li class="active"><a rel="'.$all.'" href="#">All</a></li>';
						
												$terms = get_terms($taxonomy, $args = array('orderby'=>'id')	);
												if($terms) {
													foreach($terms as $term) {
														if(isset($filter_type_array)) {
															if(in_array($term->slug,$filter_type_array)) {
																if($term->name == 'Uncategorized') { continue; }
																$text .= '<li><a rel="'.$term->slug.'" href="#">'.$term->name.'</a></li>';
															}
														} else {
															if($term->name == 'Uncategorized') { continue; }
															$text .= '<li><a rel="'.$term->slug.'" href="#">'.$term->name.'</a></li>';
														}
													}
												}
			$text .= '						</ul>';
			
			$text .= '						<input type="hidden" name="filingType" />';
			
			$text .= '						<div class="form-group visible-xs" style="margin-left: -15px;">';
			
			$text .= '							<label for="category"><strong>Display:&nbsp;</strong></label>';
			$text .= '							<select name="category">';
			$text .= '								<option value="all">All</option>';
													$terms = get_terms($taxonomy, $args = array('orderby'=>'id')	);
													if($terms) {
													
														foreach($terms as $term) {
															if(isset($filter_type_array)) {
																if(in_array($term->slug,$filter_type_array)) {
																	if($term->name == 'Uncategorized') { continue; }
																	$text .= '<option value="'.$term->slug.'">'.$term->name.'</option>';
																}
															} else {
																if($term->name == 'Uncategorized') { continue; }
																	$text .= '<option value="'.$term->slug.'">'.$term->name.'</option>';
															}
														}
													}
			$text .= '							</select>';
			$text .= '						</div>';
			$text .= '					</div>';
		}
 	} 
	
	/****************************
	Search Filters
	****************************/
	$text .= '					<div class="afs-Filters col-xs-12">';
	$text .= '						<div class="row">';
	$text .= '							<div class="afs-FilterPanel1 col-xs-12 col-sm-12">';
	$text .= '								<h5>Search</h5>';
	$text .= '								<div class="">';
	$text .= '									<div class="row rowtop">';
	
	$text .= '										<!-- SEARCH -->';
	$text .= '										<div class="form-group-inline has-feedback">';
	$text .= '											<div class="col-xs-12 col-sm-5">';
	$text .= '												<input type="text" class="form-control" name="filterBy">';
	$text .= '												<span class="fa fa-search form-control-feedback filterBy"></span>';
	$text .= '											</div>';
	$text .= '										</div>';
	$text .= '										<!-- END SEARCH -->';
											
	$text .= '										<!-- FILTER: MONTHS -->';
	$text .= '										<div class="form-group-inline">';
	$text .= '											<div class="col-xs-12 col-sm-4">';
	$text .= '												<select class="form-control" name="filterMonths">';
	$text .= '													<option value="">All Months</option>';
	$text .= '													<option value="1">January</option>';
	$text .= '													<option value="2">February</option>';
	$text .= '													<option value="3">March</option>';
	$text .= '													<option value="4">April</option>';
	$text .= '													<option value="5">May</option>';
	$text .= '													<option value="6">June</option>';
	$text .= '													<option value="7">July</option>';
	$text .= '													<option value="8">August</option>';
	$text .= '													<option value="9">September</option>';
	$text .= '													<option value="10">October</option>';
	$text .= '													<option value="11">November</option>';
	$text .= '													<option value="12">December</option>';
	$text .= '												</select>';
	$text .= '											</div>';
	$text .= '										</div>';
	$text .= '										<!-- END FILTER: MONTHS -->';
											
	$text .= '										<!-- FILTER: YEARS -->';
	$text .= '										<div class="form-group-inline">';
	$text .= '											<div class="col-xs-12 col-sm-3">';
	$text .= '												<select class="form-control" name="filterYears">';
	$text .= '													<option value="">All Years</option>';
										 						
																$years = wp_get_archives(array('type'=>'yearly','echo'=>0, 'post_type' => $post_type));
																$years = explode( '</li>' , $years );
																$years_array = array();
																foreach( $years as $link ) {
																	$link = str_replace( array( '<li>' , "\n" , "\t" , "\s" ), '' , $link );
																	if( '' != $link )
																		$years_array[] = $link;
																	else
																		continue;
																}
																foreach($years_array as $theyear) {
																	$text .= '<option value="'.strip_tags($theyear).'">'.$theyear.'</option>';
																}
										
	$text .= '												</select>';
	$text .= '											</div>';
	$text .= '										</div>';
	$text .= '										<!-- END FILTER: YEARS -->';
    $text .= '                   				</div>';
    $text .= '                				</div>';

    $text .= '       						<div class="row">';
    $text .= '            						<div class="col-sm-12">';
    $text .= '                						<div class="pull-right">';
    $text .= '                   						<button type="button" id="updateBtn" class="btn btn-primary">Update</button>';
	$text .= '											<button type="button" id="resetBtn" class="btn btn-default reset">Reset</button>';
    $text .= '               						</div>';
    $text .= '               						<div class="clearfix"></div>';
    $text .= '                 					</div>';
    $text .= '          						</div>';
    $text .= '         					</div>';                            
    $text .= '						</div>';
    $text .= '					</div>';
	
    $text .= '				</div><!-- END .col-xs-1TableRowItem2 -->';
    $text .= '				<div class="clearfix"></div>';
				
	if(AFSAdmin::afs_retrieve('_general_views') == 1) { 
            
	$text .= '				<div id="newsViewOptionsPanel" class="afs-Switch col-xs-12" style="display: block;">';
	$text .= '					<ul class="pull-right">';
	$text .= '						<li class="active"><a rel="listPR" href="javascript:;"><span class="fa fa-list-ul"></span>&nbsp;List View</a></li>';
	$text .= '						<li><a rel="gridPR" href="javascript:;"><span class="fa fa-th"></span>&nbsp;Grid View</a></li>';
	$text .= '					</ul>';
	$text .= '				</div>';
	
	} else { 
            	
    $text .= '        		<br />';
			
	}
	
	/****************************
	Begin Feed Area
	****************************/
	$text .= '				<div class="clearfix"></div>';
	$text .= '				<div class="afs-Panel afs-Panel_all col-xs-12">';
	$text .= '					<div id="newsPanel" class="scroll">';
	$text .= '						<div class="afs-TableWrapper" style="display: block;">';
	$text .= '							<div class="row">';
	
	$text .= '								<div class="afs-Table col-xs-12" style="padding-left:0; padding-right:0;">';
	$text .= '									<div class="afs-TableHeader col-sm-12 hidden-xs" style="padding-left: 0; padding-right: 0;">';
	$text .= '										<div class="col-xs-2">Date</div>';
	$text .= '										<div class="col-xs-10">Headline</div>';
	$text .= '									</div>';
	$text .= '									<div class="clearfix"></div>';
	
	
	/****************************
	Get The Feed
	****************************/
	$text .= '									<div id="newsPanelResults" class="jscroll-inner">';
	$text .= '										[afs_feed '.$afs_args.']'; // <-- the shortcode
	$text .= '										<div class="clearfix"></div>';
	$text .= '									</div>';
	$text .= '									<div class="clearfix"></div>';
	$text .= '								</div>';
	
	/****************************
	Close Feed Area
	****************************/
	$text .= '								<div class="clearfix"></div>';
	$text .= '							</div>';
	$text .= '						</div>';
	$text .= '					</div>';
	$text .= '				</div>';
	$text .= '				<div class="clearfix"></div>';
	
	
	/****************************
	Template Footer
	****************************/
    $text .= '        	</div>';
    $text .= '    	</form>';
	$text .= '	</div>';
	$text .= '</div>';
	
	return do_shortcode($text);

}

add_shortcode("ajax_filter_search", "ajax_filter_search");
	

// [afs_feed]
function afs_feed($atts, $content = null) {
    extract(shortcode_atts(array(
		'post_type'				=> AFSAdmin::afs_retrieve('_general_post_type'),
		'post_tax' 				=> AFSAdmin::afs_retrieve('_general_post_taxonomy'),
		'posts_per_page'		=> AFSAdmin::afs_retrieve('_general_posts_per_page'),
		'filter_type' 			=> '',
		'filter_by' 				=> '',
		'filter_months' 			=> '',
		'filter_years' 			=> '',
		'filter_withPDF' 		=> '',
		'offset' 				=> 0,
    ), $atts));
	
	$text = '';
	$i = 1;
	
	define('FILTER_TYPE', $filter_type);
	
	/****************************
	Define The Args & Defaults
	****************************/
	
	$offset_pag = $offset;
	if($filter_type == 'all' ) { $filter_type = ''; }
	if($offset != 0) {  $offset = ($offset - 1) * $posts_per_page; }
	if($posts_per_page == '') { get_option( 'posts_per_page' ); }

	$args = array(
		'post_type'			=> $post_type,
		'posts_per_page' 	=> $posts_per_page,
		'offset'				=> $offset,
		'date_query' 		=> array(array()),
		'orderby' 			=> 'date',
		'order'   			=> 'DESC',
	);
	
	if($filter_by !== '') { $args['s'] = $filter_by; }
	if($filter_years !== '') { $args['date_query'][]['year'] = $filter_years; }
	if($filter_months !== '') { $args['date_query'][]['month'] = $filter_months; }
	if($post_tax == 'none' || $post_tax == '') {
		// do nothing
	} else {
		if($post_tax == 'category') {
			$args['category_name'] = $filter_type;
		} elseif($post_tax == 'post_tag') {
			$args['tag'] = $filter_type;
		} else {
			// It's a custom post type:
			if($filter_type !== '') {
				
				$filter_type = explode(',',$filter_type);				
				$tax_array = array(
								'taxonomy' => $post_tax,
								'field' => 'slug',
								'terms' => $filter_type,
							);
				$args['tax_query'][] = $tax_array;
			}
			
			
		}
	}
	
	$query = new WP_Query($args);

	if ( $query->have_posts() ) { 

		$total_count 			= $query->found_posts;
		//$current_page_number   	= get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		if($offset == 0) {
			$current_page_number = 1;
		} else {
			$current_page_number = $offset;
		}

		//$posts_pp  				= get_option( 'posts_per_page' );
		$posts_pp  				= $posts_per_page;
		$posts_per_page 		= $current_page_number * $posts_pp;

		//$current_post_position = ($posts_per_page - $posts_pp) + 1;
		$current_post_position = $offset + 1;

		if($posts_per_page > $total_count) { $posts_per_page = $total_count; }
		
		$n = 1;
		$mar = 'margin-left:0;';
		while ( $query->have_posts() ) { $query->the_post();
			
			$text .= '<div id="post-'.get_the_ID().'" class="'.join( '  ', get_post_class( 'afs-Loadingdata afs-HasGA afs-TableRowItem gridPR'.$i )).'" role="article" itemscope itemtype="http://schema.org/BlogPosting" style="'.$mar.'">';
            
			$text .= return_template();
			
            $text .= '<div class="clearfix"></div>';
        	$text .= '</div>';
			
			if($n % 3 == 0) { $text .= '<div class="clearfix"></div>'; }
			if($n % 3 == 0) { $mar = 'margin-left:0;';} else { $mar = ''; }
			
			$i++; 
			$n++;
		} 

			// Pagination
			$text .= '<div class="row">';
			$text .= '	<div class="col-xs-12">';
			$text .= '		Displaying '.$current_post_position.' to <span id="pageLastRecord">'.$posts_per_page.'</span> (of <span id="recordCount">'.$total_count.'</span>)';
			$text .= '	</div>';
			$text .= '	<div class="clearfix"></div>';
			$text .= '</div>';

			$text .= '<div class="row">';
			$text .= '	 <div class="col-md-12">';
			$text .= 		afs_page_navi( array('echo' => false, 'custom_query' => $query, 'offset' => $offset_pag));
			$text .= '	</div>';
			$text .= '</div>';

	} else {

		$text .= 'No data found...';

	}

	wp_reset_query();

	return $text;

}

add_shortcode("afs_feed", "afs_feed");

?>