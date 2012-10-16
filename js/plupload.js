jQuery(document).ready(function($) {
	liveblog.uploader = new wp.Uploader({

		/* Selectors */
		browser: '#liveblog-messages',
		dropzone: '#liveblog-form-entry',

		/* Callbacks */
		success  : function( upload ) {
			var url = upload.attributes.url || upload.url,
				filename = upload.attributes.filename || upload.filename;
			
			$( '#liveblog-form-entry' ).val( $( '#liveblog-form-entry' ).val() + '<img src="' + url + '" />' );
			$( '#liveblog-messages' ).html( filename + ' Finished' );
			$( '#liveblog-actions' ).removeClass( 'uploading' );
		},
 
		error    : function ( reason ) {
			$( '#liveblog-messages' ).html( reason );
		},

		added    : function() {
			$( '#liveblog-actions' ).addClass( 'uploading' );
		},

		progress : function( upload, file ) {
			var filename, percent;

			if ( 'undefined' === typeof( file ) )
				file = upload.attributes.file;

			if( 'undefined' === typeof( file ) )
				return;

			filename = file.name;
			percent = file.percent;

			$( '#liveblog-messages' ).html( "Uploading: " + filename + ' ' + percent + '%' );
		},

		complete : function() {
			$( '#liveblog-messages' ).html( 'All done!' );
		}
	});
});
