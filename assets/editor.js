(function ($, options) {
	$(document).ready(function () {
		var textarea = document.getElementById( options.fieldId );

		var editor = CodeMirror.fromTextArea( textarea, options.codeMirror );

		var height = $('html').height() - $( editor.getWrapperElement() ).offset().top;
		editor.setSize( null, height );
	});
})(jQuery, cceFileEditor);