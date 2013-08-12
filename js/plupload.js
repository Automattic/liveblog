jQuery(document).ready(function($) {
	liveblog.uploader = new wp.Uploader({

		/* Selectors */
		// @todo selctors need to be class-based and the upload.dropzone needs to be used to select the current message and container
		browser: '#liveblog-messages',
		dropzone: '#liveblog-container',

		/* Callbacks */
		success  : function( upload ) {
			var url,
				filename = upload.attributes? upload.attributes.filename : upload.filename,
				$textarea = this.dropzone.find( '.liveblog-form-entry:first' ),
				$contenteditable = this.dropzone.find( '.liveblog-form-rich-entry:first' ),
				entry_text = $textarea.val();

			if ( upload.attributes ) {
				if ( upload.attributes.sizes && upload.attributes.sizes.large )
					url = upload.attributes.sizes.large.url;
				else
					url = upload.attributes.url;
			} else {
				url = upload.url;
			}

			if ( entry_text )
				entry_text += "\n";
			entry_text += url + "\n";

			$textarea.val( entry_text );
			$textarea.trigger('input');
			$( '#liveblog-messages' ).html( filename + ' Finished' );
			$( '#liveblog-actions' ).removeClass( 'uploading' );

			if ($contenteditable.is(':visible')) {
				$contenteditable.focus();
			}
			else {
				$textarea.focus();
			}
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
