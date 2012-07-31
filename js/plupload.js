jQuery(document).ready(function($) {
	new wp.Uploader({

	    /* Selectors */
		browser:   '#liveblog-actions legend',
	    dropzone:  '#liveblog-form-entry',

	    /* Callbacks */
	    success  : function( attachment ) {
			$( '#liveblog-form-entry'     ).val( $('#liveblog-form-entry' ).val() + '<img src="' + attachment.url + '" />' );
			$( '#liveblog-actions legend' ).html( attachment.filename + ' Finished' );
			$( '#liveblog-actions'        ).removeClass( 'uploading' );
	    },
 
		error    : function ( reason ) {
			$( '#liveblog-actions legend' ).html( reason );
		},

		added    : function() {
			$( '#liveblog-actions' ).addClass( 'uploading' );
		},

		progress : function( up, file ) {
			$( '#liveblog-actions legend' ).html( "Uploading: " + file.name + ' ' + file.percent + '%' );
		},

		complete : function() {
			$( '#liveblog-actions legend' ).html( 'All done!' );
		}
	});
});
