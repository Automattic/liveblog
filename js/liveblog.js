var liveblog = {};

( function( $ ) {

	liveblog.init = function() {
		liveblog.set_timestamp( liveblog_settings.last_timestamp );

		liveblog.$entry_container = $( '.liveblog-entries' );

		// Damn you wp_localize_script
		liveblog_settings.refresh_interval = parseInt( liveblog_settings.refresh_interval );
		liveblog_settings.max_retries = parseInt( liveblog_settings.max_retries );
		liveblog_settings.delay_threshold = parseInt( liveblog_settings.delay_threshold );
		liveblog_settings.delay_multiplier = parseFloat( liveblog_settings.delay_multiplier );

		liveblog.reset_timer();
	}

	liveblog.kill_timer = function() {
		clearTimeout( liveblog.refresh_timeout );
	}
	liveblog.reset_timer = function() {
		liveblog.kill_timer();
		liveblog.refresh_timeout = setTimeout( liveblog.get_recent_entries, ( liveblog_settings.refresh_interval * 1000 ) );
	}
	liveblog.undelay_timer = function() {
		if ( liveblog_settings.original_refresh_interval )
			liveblog_settings.refresh_interval = liveblog_settings.original_refresh_interval;

		console.log( 'undelay timer', liveblog_settings.refresh_interval );
	}
	liveblog.delay_timer = function() {
		if ( ! liveblog_settings.original_refresh_interval )
			liveblog_settings.original_refresh_interval = liveblog_settings.refresh_interval;

		liveblog_settings.refresh_interval *= liveblog_settings.delay_multiplier;

		console.log( 'delay timer', liveblog_settings.refresh_interval );
	}
	liveblog.set_timestamp = function( timestamp ) {
		liveblog.last_timestamp = timestamp;
	}
	liveblog.get_timestamp = function( timestamp ) {
		return liveblog.last_timestamp;
	}

	liveblog.get_recent_entries = function() {
		// TODO: Show loading

		var url = liveblog_settings.entriesurl,
			timestamp = liveblog.get_timestamp();

		if ( timestamp )
			url += timestamp + '/';
		liveblog.ajax_request( url, {}, liveblog.get_recent_entries_success, liveblog.get_recent_entries_error );
	}

	liveblog.get_recent_entries_success = function( data ) {
		console.log( 'SUCCESS - get_recent_entries_success', data );

		if ( ! data.data.entries || ! data.data.entries.length ) {
			liveblog.get_recent_entries_error( data );
			return;
		}

		liveblog.display_entries( data.data.entries );

		// TODO: highlight updated posts

		liveblog.set_timestamp( data.data.timestamp );
		liveblog.reset_timer();
		liveblog.undelay_timer();
	}

	liveblog.get_recent_entries_error = function( data ) {
		console.log( 'FAIL - get_recent_entries_error', arguments, liveblog.failure_count );

		// Have a max number of checks, which causes the auto-update to shut off or slow down the auto-update
		if ( ! liveblog.failure_count )
			liveblog.failure_count = 0;

		liveblog.failure_count++;

		if ( 0 == liveblog.failure_count % liveblog_settings.delay_threshold ) {
			liveblog.delay_timer();
		}

		if ( liveblog.failure_count >= liveblog_settings.max_retries ) {
			liveblog.kill_timer();
			// TODO: show message that live refresh is disabled; show click-to-enable
			return;
		}

		liveblog.reset_timer();
	}

	liveblog.display_entries = function( entries ) {
		console.log( 'display_entries', entries );

		for ( var i in entries ) {
			var entry = entries[i];
			liveblog.display_entry( entry );
		}

		liveblog.show_nag( entries );
	}

	liveblog.show_nag = function( entries ) {
		var show_nag = true == liveblog.did_first_request,
			hidden_entries = liveblog.get_hidden_entries(),
			hidden_entries_count = hidden_entries.length;

		if ( ! show_nag || liveblog.is_nag_disabled() ) {
			liveblog.unhide_entries();
			liveblog.did_first_request = true;
			return;
		}

		// Update count in title
		if ( ! liveblog.original_title )
			liveblog.original_title = document.title;

		document.title = '(' + hidden_entries_count + ') ' + document.title;

		if ( ! liveblog.$update_nag ) {
			liveblog.$update_nag = $( '<div/>' );
			liveblog.$update_nag
				.addClass( 'liveblog-nag liveblog-message' )
				.hide();
		}

		var nag_text = 1 < hidden_entries_count ? liveblog_settings.update_nag_plural : liveblog_settings.update_nag_singular;
		nag_text = nag_text.replace( '%d', hidden_entries_count );

		liveblog.$update_nag
			.html( nag_text )
			.prependTo( liveblog.$entry_container )
			.one( 'click', function() {
				liveblog.unhide_entries();
				$( this ).hide();
				document.title = liveblog.original_title;
			} )
			.slideDown();
	}
	liveblog.disable_nag = function() {
		liveblog.nag_disabled = true;
	}
	liveblog.is_nag_disabled = function() {
		return liveblog.nag_disabled;
	}

	liveblog.display_entry = function( entry ) {
		// If the entry is already there, update it
		var $entry = $( '#liveblog-entry-' + entry.ID );
		if ( $entry.length ) {
			$entry.replaceWith( entry.content )
				.addClass( 'liveblog-updated' )
				.one( 'mouseover', function() {
					$( this ).removeClass( 'liveblog-updated' );
				} );
		} else {
			$entry = $( entry.content );
			$entry.addClass( 'liveblog-hidden' ).prependTo( liveblog.$entry_container );
		}
	}

	liveblog.get_all_entries = function() {
		return liveblog.$entry_container.find( '.liveblog-entry' );
	}
	liveblog.get_hidden_entries = function() {
		return liveblog.get_all_entries().filter( '.liveblog-hidden' );
	}
	liveblog.get_visible_entries = function() {
		return liveblog.get_all_entries().not( '.liveblog-hidden' );
	}
	liveblog.unhide_entries = function() {
		liveblog.get_hidden_entries().removeClass( 'liveblog-hidden' );
	}

	liveblog.ajax_request = function( url, data, success_callback, error_callback, method ) {
		console.log( 'Making liveblog ajax request', arguments );

		if ( 'function' !== typeof( success_callback ) )
			success_callback = liveblog.success_callback;

		if ( 'function' === typeof( error_callback ) )
			error_callback = liveblog.error_callback;

		method = method || 'GET';

		$.ajax( {
			url: url,
			data: data,
			type: method,
			dataType: 'json',
			success: function( data ) {
				console.log( 'AJAX call success', arguments );
				if ( 1 == data.status )
					success_callback( data );
				else
					error_callback( data );
			},
			error: function( data ) {
				console.log( 'AJAX call error', arguments );
				error_callback( data );
			}
		} );
	}
	liveblog.success_callback = function() {}
	liveblog.error_callback = function() {}

	// Initialize everything!
	$( document ).ready( liveblog.init );

} )( jQuery );
