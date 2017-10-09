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
		liveblog.socketio.socket = new io( liveblog_socketio_settings.url );

		liveblog.socketio.socket.on( 'connect', liveblog.socketio.send_post_key );
		liveblog.socketio.socket.io.on( 'connect_error', liveblog.socketio.connect_error );
		liveblog.socketio.socket.io.on( 'reconnect' , liveblog.socketio.reconnect );
		liveblog.socketio.socket.on( 'liveblog entry', liveblog.socketio.process_entry );
	};

	/**
	 * Send the post key to the Socket.io server.
	 */
	liveblog.socketio.send_post_key = function() {
		liveblog.socketio.socket.emit( 'post key', liveblog_socketio_settings.post_key );
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
	 * Receive a message from the Socket.io server with a new,
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
