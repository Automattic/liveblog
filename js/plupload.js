jQuery(document).ready(function($) {
	liveblog.uploader = new wp.Uploader({

		/* Selectors */
		browser: '#liveblog-messages',
		dropzone: '#liveblog-container',

		/* Callbacks */
		success  : function( upload ) {
			var url = upload.attributes? upload.attributes.url : upload.url,
				filename = upload.attributes? upload.attributes.filename : upload.filename,
				$form = $( '.liveblog-form-entry' ),
				entry_text = $form.val();

			if ( entry_text )
				entry_text += "\n";
			entry_text += url + "\n";

			$form.val( entry_text );
			$( '#liveblog-messages' ).html( filename + ' Finished' );
			$( '#liveblog-actions' ).removeClass( 'uploading' );

			$form.focus();
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
				file = upload.attributes? upload.attributes.file : upload.file;

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
