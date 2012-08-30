jQuery(document).ready(function($) {
	liveblog.uploader = new wp.Uploader({

	    /* Selectors */
		browser:   '#liveblog-messages',
	    dropzone:  '#liveblog-form-entry',

	    /* Callbacks */
	    success  : function( attachment ) {
			$( '#liveblog-form-entry'     ).val( $('#liveblog-form-entry' ).val() + '<img src="' + attachment.url + '" />' );
			$( '#liveblog-messages' ).html( attachment.filename + ' Finished' );
			$( '#liveblog-actions'        ).removeClass( 'uploading' );
	    },
 
		error    : function ( reason ) {
			$( '#liveblog-messages' ).html( reason );
		},

		added    : function() {
			$( '#liveblog-actions' ).addClass( 'uploading' );
		},

		progress : function( up, file ) {
			$( '#liveblog-messages' ).html( "Uploading: " + file.name + ' ' + file.percent + '%' );
		},

		complete : function() {
			$( '#liveblog-messages' ).html( 'All done!' );
		}
	});
});
