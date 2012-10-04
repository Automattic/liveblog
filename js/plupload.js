jQuery(document).ready(function($) {
	liveblog.uploader = new wp.Uploader({

		/* Selectors */
		browser:   '#liveblog-messages',
		dropzone:  '#liveblog-form-entry',

		/* Callbacks */
		success  : function( attachment ) {
			$( '#liveblog-form-entry'     ).val( $('#liveblog-form-entry' ).val() + '<img src="' + attachment.attributes.url + '" />' );
			$( '#liveblog-messages' ).html( attachment.attributes.filename + ' Finished' );
			$( '#liveblog-actions'        ).removeClass( 'uploading' );
		},

		error    : function ( reason ) {
			$( '#liveblog-messages' ).html( reason );
		},

		added    : function() {
			$( '#liveblog-actions' ).addClass( 'uploading' );
		},

		progress : function( up ) {
			$( '#liveblog-messages' ).html( "Uploading: " + up.attributes.file.name + ' ' + up.attributes.file.percent + '%' );
		},

		complete : function() {
			$( '#liveblog-messages' ).html( 'All done!' );
		}
	});
});
