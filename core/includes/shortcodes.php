<?php 
/*******************************************************************
* BUILD THE SHORTCODE
********************************************************************/
// [ajax_filter_search]
function ajax_filter_search($atts, $content = null) {
    extract(shortcode_atts(array(
    ), $atts));
	
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
	if(AFSAdmin::afs_retrieve('_general_show_filters') == 1) { 
                
	$text .= '					<div class="afs-Tabs col-xs-12">';
	$text .= '						<ul class="hidden-xs afs-CommonTabs">';
	$text .= '							<li class="active"><a rel="all" href="#">All</a></li>';
				
										$taxonomy = AFSAdmin::afs_retrieve('_general_post_taxonomy');
										$terms = get_terms($taxonomy, $args = array('orderby'=>'id')	);
										foreach($terms as $term) {
											if($term->name == 'Uncategorized') { continue; }
											$text .= '<li><a rel="'.$term->slug.'" href="#">'.$term->name.'</a></li>';
										}
				
	$text .= '						</ul>';
	
	$text .= '						<input type="hidden" name="filingType" />';
	
	$text .= '						<div class="form-group visible-xs" style="margin-left: -15px;">';
	
	$text .= '							<label for="category"><strong>Display:&nbsp;</strong></label>';
	$text .= '							<select name="category">';
	$text .= '								<option value="all">All Releases</option>';
											foreach($terms as $term) {
												if($term->name == 'Uncategorized') { continue; }
												$text .= '<option value="'.$term->slug.'">'.$term->name.'</option>';
											}
	$text .= '							</select>';
	$text .= '						</div>';
	$text .= '					</div>';
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
										 
																$years = wp_get_archives(array('type'=>'yearly','echo'=>0));
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
	$text .= '										[afs_feed]'; // <-- the shortcode
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
		'post_type'			=> AFSAdmin::afs_retrieve('_general_post_type'),
		'posts_per_page'	=> AFSAdmin::afs_retrieve('_general_posts_per_page'),
		'filter_type' 		=> '',
		'filter_by' 			=> '',
		'filter_months' 		=> '',
		'filter_years' 		=> '',
		'filter_withPDF' 	=> '',
		'offset' 			=> 0,
    ), $atts));
	
	$text = '';
	$i = 1;
		
	
	/****************************
	Define The Args & Defaults
	****************************/
	
	$offset_pag = $offset;
	if($filter_type == 'all' ) { $filter_type = ''; }
	if($offset != 0) {  $offset = ($offset - 1) * $posts_per_page; }
	if($posts_per_page == '') { get_option( 'posts_per_page' ); }

	$args = array(
		'post_type'			=> $post_type,
		'category_name' 		=> $filter_type,
		'posts_per_page' 	=> $posts_per_page,
		'offset'				=> $offset,
		'date_query' 		=> array(array()),
		'orderby' 			=> 'date',
		'order'   			=> 'DESC',
	);
	
	if($filter_by !== '') { $args['s'] = $filter_by; }
	if($filter_years !== '') { $args['date_query'][]['year'] = $filter_years; }
	if($filter_months !== '') { $args['date_query'][]['month'] = $filter_months; }

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
		//$current_post_position 	= $wp_query->current_post + 2;

		$current_post_position = ($posts_per_page - $posts_pp) + 1;

		if($posts_per_page > $total_count) { $posts_per_page = $total_count; }

		while ( $query->have_posts() ) { $query->the_post();

			$text .= '<div id="post-'.get_the_ID().'" class="'.join( '  ', get_post_class( 'afs-Loadingdata afs-HasGA afs-TableRowItem gridPR'.$i )).'" role="article" itemscope itemtype="http://schema.org/BlogPosting">';
            $text .= '<div class="prDateCol col-sm-2">';
            $text .= '	<div class="visible-xs afs-Divider"></div>';
            $text .= ' 	<div class="afs-PRDate">'.get_the_time('n/d/Y').'</div>';
       		$text .= '	<div class="afs-PRTimezone"><span class="afs-PRTime">'.get_the_time('g:i a').'</span><span class="afs-timezone">&nbsp;'.get_the_time('T').'</span></div>';
            $text .= '</div>';

            $text .= '<div class="col-sm-10">';
            $text .= '   <h6>';
			
			if(AFSAdmin::afs_retrieve('_general_post_taxonomy') != '' && AFSAdmin::afs_retrieve('_general_post_taxonomy') != 'none') {
				$cats = get_the_terms(get_the_ID(), AFSAdmin::afs_retrieve('_general_post_taxonomy'));
				foreach( $cats as $cat ) { 
					$text .= $cat->name; 
				} 
			}
			$text .= '	</h6>';

            $text .= '   <h4><a href="'.get_the_permalink().'" class="afs-GaLabel afs-EventTracking afs-GaHasFile" data-GaFID="'.get_the_ID().'">'.get_the_title().'</a></h4>';
            $text .= '    <div class="prDateRow">';
            $text .= '        <div class="afs-PRDate">'.get_the_time('n/d/Y').'</div>';
            $text .= '    </div>';
            $text .= '    <div class="afs-PRTools">';
            $text .= '        <ul>';
            $text .= '      		<li class="hideFromGrid last"><a class="showSummary" href="#"><span class="fa fa-plus-square"></span>&nbsp;Summary</a></li>';
            $text .= '       </ul>';
            $text .= '       <div class="clearfix"></div>';
            $text .= '   </div>';

            $text .= '    <div class="afs-PRSummary afs-Hidden">';
			$text .= '		<p>'.get_the_selected_excerpt(200).'...</p>';
            $text .= '        <a href="'.get_the_permalink().'" class="afs-EventTracking afs-GaHasTitle" data-GaTitle="HTM"><span class="fa fa-chevron-right"></span>&nbsp;Continue Reading</a>';
            $text .= '    </div>';

       		$text .= '   <div class="afs-PRRelated afs-PendingHide">';
         	$text .= '       <ul>';
         	$text .= '           <li class="first"><strong>Related Material:&nbsp;</strong></li>';
           	$text .= '           <li><a href="#"><span class="fa fa-microphone"></span>&nbsp;Webcast</a></li>';
            $text .= '           <li class="last"><a href="#"><span class="fa fa-download"></span>&nbsp;Download - XLS - 3.5MB</a></li>';
            $text .= '      </ul>';

            $text .= '      <div class="clearfix"></div>';
            $text .= '   </div>';
            $text .= '</div>';
            $text .= '<div class="clearfix"></div>';
        	$text .= '</div>';

			$i++; } 

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