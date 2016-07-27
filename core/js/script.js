;(function($){
	"use strict";
	
	/*****************************************
	INITIALIZE FUNCTION:
	*****************************************/
	function init() {
		
		// Show Summary
		$("a.showSummary").click(function(n) {
			n.preventDefault();
			var i = $(this).children(".fa").attr("class"),
				t = $(this).closest(".afs-PRTools").next();
			
			i === "fa fa-minus-square" ? ($(this).children(".fa").attr("class", "fa fa-plus-square"), $(this).parent().removeClass("active"), t.css("display", "none")) : ($(this).children(".fa").attr("class", "fa fa-minus-square"), $(this).parent().addClass("active"), t.css("display", "block"));
		});
		
		
		// List / Grid View Toggle
		$(".afs-Switch a").click(function(n) {
			n.preventDefault();
			var t = $(".afs-TableWrapper"),
				i = $(this).attr("rel");
		   
			t.hasClass(i) || ($(".afs-Switch li").removeClass("active"), $(this).parent().addClass("active"), t.removeAttr("class"), t.addClass("afs-TableWrapper " + i));
			
		});
		
		
		// Tab / Form Filters
		$(".afs-Tabs a").click(function(n) {
			$("input[name='filingType']").val(n.target.rel), 
			$("select[name='category']").val(n.target.rel), 
			submitForm($(this));
			return false;
		}), 
		$("#newsForm input[name='filterBy']").keydown(function(n) {
			var t = n.keyCode || n.which;
			t === 13 && submitForm();
		}), 
		$("#updateBtn, .filterBy").click(function() {
			submitForm();
			return false;
		}), 
		$("#resetBtn").click(function() {
			var t = $("input[placeholder='Email']").val();
			$("#newsForm")[0].reset(), 
			$("input[placeholder='Email']").val(t), 
			$("select[name='category']").val($("input[name='filingType']").val()), 
			submitForm();
			return false;
		});
		
		// Pagination Buttons
		$('.post-nav ul a').click(function() { 
			
			$('.post-nav ul a').addClass('btn-default').removeClass('btn-primary');
			$(this).addClass('btn-primary').removeClass('btn-default');
			var n = $(this).text();
			
			if($(this).attr('title') === 'previous') {
				n = $('.post-nav ul a.btn-primary').attr('href');
			} else if($(this).attr('title') === 'next') {
				n = $('.post-nav ul a.btn-primary').attr('href');
			} 
			
			parseInt(n, 10);
			submitForm(n);
			return false;
			
		});
		
	}
	
	
	/*****************************************
	PROCESS FORM DATA:
	*****************************************/
	var submitForm = function(n) {		
		 // Get the info
		var t;
		t = "filingType=" + encodeURIComponent(
			$("<div/>").text($("input[name='filingType']").val()).html()
		), 
		t += "&filterBy=" + encodeURIComponent($("<div/>").text($("input[name='filterBy']").val()).html()), 
		t += "&filterMonths=" + (typeof $("select[name='filterMonths']").val() !== "undefined" ? $("select[name='filterMonths']").val() : ""), 
		t += "&filterYears=" + (typeof $("select[name='filterYears']").val() !== "undefined" ? $("select[name='filterYears']").val() : ""), 
		t += "&withPDF=" + $("input[name='withPDF']").is(":checked");
		
		if(n !== undefined) {
			if(isNaN(n)) {
				// Add/Remove Active class
				$(".afs-Tabs a").parent('li').removeClass('active');
				n.parent('li').addClass('active');
			} else {
				// Pagination Link
				t +='&page='+n;
				//console.log(n);
			}
		}
			
		// Submit the form and do animations
		$("#newsPanelResults").stop().animate({ "opacity":"0.5"}, 'fast', function(){
			
			// Perform Ajax
			$.ajax({
				type: 'POST',
				url : ajaxurl,
				cache: false,
				data : 'action=my_action&'+t,
				complete : function() {  },
				success: function(data) {
					$('#newsPanelResults').html(data).stop().animate({ opacity: 1 }, 'fast' );
					init();
				}
			});
		});
	
	};
	
	/*****************************************
	STANDARD STUFF
	*****************************************/
		$(document).ready(function () {
			
			// Initialize everything
			init();
		
		});
})(jQuery);