$(function() {
	
	$(".input-datetime").datepicker();
	
	$("textarea.form-control").ckeditor({
	        toolbar:
	        [
	            ['Bold', 'Italic', 'Underline', '-', 'NumberedList', 'BulletedList', '-', 'Link'],
	            ['UIColor']
	        ]
	});
	
	$(".select").select2({
		placeholder: "Select a table",
	    width: "element"
	});
	
});
