<?php 	
if(FILTER_TYPE == '') { 
	// do nothing
} else { 
	$filter_type_array = explode(',',FILTER_TYPE);
	$filter_type_count = count($filter_type_array);
}
?>
<div class="prDateCol col-sm-2">
	<div class="visible-xs afs-Divider"></div>
		<div class="afs-PRDate"><?php echo get_the_time('n/d/Y'); ?></div>
		<div class="afs-PRTimezone"><span class="afs-PRTime"><?php echo get_the_time('g:i a'); ?></span><span class="afs-timezone">&nbsp;<?php echo get_the_time('T'); ?></span></div>
   	</div>

	<div class="col-sm-10">
		<h6>
			<?php 
			if(AFSAdmin::afs_retrieve('_general_post_taxonomy') != '' && AFSAdmin::afs_retrieve('_general_post_taxonomy') != 'none') {
				$cats = get_the_terms(get_the_ID(), AFSAdmin::afs_retrieve('_general_post_taxonomy'));
				$cat_count = count($cats);
				
				$i = 1;
				if($cats) { // meow
					foreach( $cats as $cat ) {
						if(isset($filter_type_array)) {
							if(in_array($cat->slug,$filter_type_array)) {
								$comma = ', '; 
								if($cat_count == $i) { $comma = ''; } 
								echo $cat->name.$comma; 
								$i++;
							}
						} else {
							$comma = ', '; 
							if($cat_count == $i) { $comma = ''; } 
							echo $cat->name.$comma; 
							$i++;
						}
					} 
				}
			}
			?>
		</h6>

		<h4><a href="<?php echo get_the_permalink(); ?>" class="afs-GaLabel afs-EventTracking afs-GaHasFile" data-GaFID="<?php echo get_the_ID(); ?>"><?php echo get_the_title(); ?></a></h4>
 		<div class="prDateRow">
      		<div class="afs-PRDate"><?php echo get_the_time('n/d/Y'); ?></div>
      	</div>
   		<div class="afs-PRTools">
        	<?php 
				$summary = get_the_selected_excerpt(200); 
				if(get_the_selected_excerpt(200) == '') {
				} else {
			?>
     		<ul>
    			<li class="hideFromGrid last"><a class="showSummary" href="#"><span class="fa fa-plus-square"></span>&nbsp;Summary</a></li>
 			</ul>
            <?php } ?>
  			<div class="clearfix"></div>
    	</div>

 		<div class="afs-PRSummary afs-Hidden">
			<p><?php echo $summary; ?>...</p>
   				<a href="<?php echo get_the_permalink(); ?>" class="afs-EventTracking afs-GaHasTitle" data-GaTitle="HTM"><span class="fa fa-chevron-right"></span>&nbsp;Continue Reading</a>
      	</div>
    	
</div>