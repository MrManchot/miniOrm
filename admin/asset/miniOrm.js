$(function() {
	$(".input-datetime").datepicker();
	$("textarea.form-control").ckeditor();
	$(".select").select2({
		placeholder: "Select a table",
	    width: "element"
	});
});