( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' ) {
		return;
	}

	liveblog.socketio = {};

	/**
	 * Initialize a connection with the Socket.io server
	 * and register callbacks for Socket.io events.
	 */
	liveblog.socketio.init = function() {
		var socket = new io( liveblog_socketio_settings.url );

		socket.io.on( 'connect_error', liveblog.socketio.connect_error );
		socket.io.on( 'reconnect' , liveblog.socketio.reconnect );
		socket.on( 'liveblog entry ' + liveblog_settings.post_id, liveblog.socketio.process_entry );
	};

	/**
	 * Show error message if unable to connect to
	 * the Socket.io server.
	 */
	liveblog.socketio.connect_error = function() {
		// TODO: maybe reuse liveblog.FixedNagView to display this error?
		if ( $( '#wpadminbar' ).length) {
			$( '#liveblog-socketio-error-container' ).css( 'top', $( '#wpadminbar' ).height() );
		}

		$( '#liveblog-socketio-error' ).html( liveblog_socketio_settings.unable_to_connect );
		$( '#liveblog-socketio-error-container' ).show();
	};

	/**
	 * Hide error message when connection to the Socker.io
	 * server is reestablished
	 */
	liveblog.socketio.reconnect = function () {
		$( '#liveblog-socketio-error-container' ).hide();
	};

	/**
	 * Receive a message from the Socket.io server with an new,
	 * updated or deleted entry and call the method that will
	 * decide whether to display it, add it to queue or remove it.
	 *
	 * Also remove the edit and delete buttons from the entry HTML
	 * if the user doesn't have permission to change it. This has to
	 * be done in the JS since the message is always emitted in PHP
	 * by a user who can change a Liveblog entry and the Socket.io
	 * server isn't aware of the permissions of its clients.
	 */
	liveblog.socketio.process_entry = function( entry ) {
		var entry_object = $.parseJSON( entry );

		if ( ! liveblog_settings.is_liveblog_editable ) {
			// If user doesn't have permission to edit or delete entries remove the action buttons
			var entry_html = $( entry_object.html );
			entry_html.find( '.liveblog-entry-actions' ).remove();
			entry_object.html = entry_html.prop( 'outerHTML' );
		}

		liveblog.maybe_display_entries( [ entry_object ], 5000 );
	};

	if ( liveblog_settings.socketio_enabled ) {
		liveblog.$events.bind( 'after-init', liveblog.socketio.init );
	}

} )( jQuery );
