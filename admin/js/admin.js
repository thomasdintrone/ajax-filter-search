/****************************
ADMIN JS
****************************/
(function ($) {
	"use strict";
  	$(function () {
	
		// Update Post Taxonomy based on Post Type selection:
		$('select.general-post-type').on('change', function () { 
			
			$('#submit').attr('disabled', 'disabled');
			
			// Get Post Selected:
			//var optionSelected = $("option:selected", this);
			var valueSelected = this.value;
			
			$('select.general-post-taxonomy').stop().animate({ opacity:0 }, function(){
				$.ajax({
					type: 'POST',
					url : ajaxurl,
					cache: false,
					data : 'action=get_selected&option='+valueSelected,
					complete : function() {  },
					success: function(data) {
						$('select.general-post-taxonomy').html(data).stop().animate({ opacity: 1 }, 'fast' );
						$('#submit').removeAttr('disabled');
					}
				});
			});
		});
		
		// Activate Colorpicker
		$('.plugin-colorpicker').wpColorPicker();
	
	});
}(jQuery));