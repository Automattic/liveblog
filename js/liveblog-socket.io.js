var socket = new io( liveblog_socketio_settings.url );

/**
 * Show error message if unable to connect to
 * the Socket.io server.
 */
socket.io.on( 'connect_error', function() {
	// TODO: maybe reuse liveblog.FixedNagView to display this error?
	if ( jQuery( '#wpadminbar' ).length) {
		jQuery( '#liveblog-socketio-error-container' ).css( 'top', jQuery( '#wpadminbar' ).height() );
	}

	jQuery( '#liveblog-socketio-error' ).html( liveblog_socketio_settings.unable_to_connect );
	jQuery( '#liveblog-socketio-error-container' ).show();
});

/**
 * Hide error message when connection to the Socker.io
 * server is reestablished
 */
socket.io.on( 'reconnect' , function() {
	jQuery( '#liveblog-socketio-error-container' ).hide();
});

socket.on( 'liveblog entry ' + liveblog_settings.post_id, function( entry ) {
	var entry_object = jQuery.parseJSON( entry );

	if ( ! liveblog_settings.is_liveblog_editable ) {
		// If user doesn't have permission to edit or delete entries remove the action buttons
		var entry_html = jQuery( entry_object.html );
		entry_html.find( '.liveblog-entry-actions' ).remove();
		entry_object.html = entry_html.prop( 'outerHTML' );
	}

	liveblog.maybe_display_entries( [ entry_object ], 5000 );
});
