var liveblog = {};

( function( $ ) {

	// A dummy proxy DOM element, which allows us to use arbitrary events
	// via the jQuery events system
	liveblog.$events = $( '<span />' );

	liveblog.init = function() {
		liveblog.$container       = $( '#liveblog-container'      );
		liveblog.$entry_container = $( '#liveblog-entries'        );
		liveblog.$spinner         = $( '#liveblog-update-spinner' );
		liveblog.paused           = false;
		liveblog.cast_settings_numbers();
		liveblog.reset_timer();
		liveblog.set_initial_timestamps();
		liveblog.$events.trigger( 'after-init' );

		//liveblog.disable_nag();
	};

	liveblog.set_initial_timestamps = function() {
		var now = liveblog.current_timestamp();
		liveblog.latest_entry_timestamp           = liveblog_settings.latest_entry_timestamp || 0;
		liveblog.latest_response_local_timestamp  = now;
		liveblog.latest_response_server_timestamp = now;
	};

	// wp_localize_scripts makes all integers into strings, and in JS
	// we need them to be real integers, so that we can use them in
	// arithmetic operations
	liveblog.cast_settings_numbers = function() {
		liveblog_settings.refresh_interval        = parseInt( liveblog_settings.refresh_interval, 10 );
		liveblog_settings.max_consecutive_retries = parseInt( liveblog_settings.max_consecutive_retries, 10 );
		liveblog_settings.delay_threshold         = parseInt( liveblog_settings.delay_threshold, 10 );
		liveblog_settings.delay_multiplier        = parseFloat( liveblog_settings.delay_multiplier, 10 );
		liveblog_settings.latest_entry_timestamp  = parseInt( liveblog_settings.latest_entry_timestamp, 10 );
	};

	liveblog.kill_timer = function() {
		clearTimeout( liveblog.refresh_timeout );
	};

	liveblog.reset_timer = function() {
		liveblog.kill_timer();
		liveblog.refresh_timeout = setTimeout( liveblog.get_recent_entries, ( liveblog_settings.refresh_interval * 1000 ) );
	};

	liveblog.undelay_timer = function() {
		if ( liveblog_settings.original_refresh_interval )
			liveblog_settings.refresh_interval = liveblog_settings.original_refresh_interval;
	};

	liveblog.delay_timer = function() {
		if ( ! liveblog_settings.original_refresh_interval )
			liveblog_settings.original_refresh_interval = liveblog_settings.refresh_interval;

		liveblog_settings.refresh_interval *= liveblog_settings.delay_multiplier;

	};

	liveblog.pause = function() {
		liveblog.paused = true;
		liveblog.$container.addClass( 'paused' );
		liveblog.enable_nag();
		liveblog.$events.trigger( 'pause' );
	};

	liveblog.play = function() {
		liveblog.paused = false;
		liveblog.$container.removeClass( 'paused' );
		liveblog.disable_nag();
		liveblog.$events.trigger( 'play' );
	};

	liveblog.get_recent_entries = function() {
		var url  = liveblog_settings.endpoint_url;
		var from = liveblog.latest_entry_timestamp + 1;

		var local_diff = liveblog.current_timestamp() - liveblog.latest_response_local_timestamp;
		var to         = liveblog.latest_response_server_timestamp + local_diff;

		url += '/' + from + '/' + to + '/';
		liveblog.show_spinner();
		liveblog.ajax_request( url, {}, liveblog.get_recent_entries_success, liveblog.get_recent_entries_error );
	};

	liveblog.get_recent_entries_success = function( response, status, xhr ) {

		liveblog.consecutive_failures_count = 0;

		liveblog.hide_spinner();

		if ( response && response.latest_timestamp )
			liveblog.latest_entry_timestamp = response.latest_timestamp;

		liveblog.latest_response_server_timestamp = liveblog.server_timestamp_from_xhr( xhr );
		liveblog.latest_response_local_timestamp  = liveblog.current_timestamp();

		liveblog.display_entries( response.entries );

		liveblog.reset_timer();
		liveblog.undelay_timer();
	};

	liveblog.get_recent_entries_error = function( response ) {

		liveblog.hide_spinner();

		// Have a max number of checks, which causes the auto-update to shut off or slow down the auto-update
		if ( ! liveblog.consecutive_failures_count )
			liveblog.consecutive_failures_count = 0;

		liveblog.consecutive_failures_count++;

		if ( 0 === liveblog.consecutive_failures_count % liveblog_settings.delay_threshold ) {
			liveblog.delay_timer();
		}

		if ( liveblog.consecutive_failures_count >= liveblog_settings.max_consecutive_retries ) {
			liveblog.kill_timer();
			return;
		}

		liveblog.reset_timer();
	};

	liveblog.display_entries = function( entries ) {

		if ( !entries || ! entries.length ) {
			return;
		}

		for ( var i = 0; i < entries.length; i++ ) {
			var entry = entries[i];
			liveblog.display_entry( entry );
		}

		liveblog.show_nag( entries );
	};

	liveblog.show_nag = function( entries ) {
		var hidden_entries = liveblog.get_hidden_entries(),
			hidden_entries_count = hidden_entries.length;

		if ( !entries || !entries.length ) {
			return;
		}

		if ( ! hidden_entries_count ) {
			return;
		}

		if ( liveblog.is_nag_disabled() ) {
			liveblog.unhide_entries();
			return;
		}

		// Update count in title
		if ( ! liveblog.original_title )
			liveblog.original_title = document.title;

		liveblog.update_count_in_title( hidden_entries_count );

		if ( ! liveblog.$update_nag ) {
			liveblog.$update_nag = $( '<div/>' );
			liveblog.$update_nag
				.addClass( 'liveblog-nag liveblog-message' )
				.slideUp();
		}

		var nag_text = 1 < hidden_entries_count ? liveblog_settings.update_nag_plural : liveblog_settings.update_nag_singular;
		nag_text = nag_text.replace( '%d', hidden_entries_count );

		liveblog.$update_nag
			.html( nag_text )
			.prependTo( liveblog.$entry_container )
			.one( 'click', function() {
				liveblog.unhide_entries();
				$( this ).slideUp();
				liveblog.play();
				document.title = liveblog.original_title;
			} )
		.slideDown();
	};

	liveblog.update_count_in_title = function( count ) {
		var count_string = '(' + count + ')';
		document.title = document.title.replace( /^\(\d+\)\s+/, '' );
		document.title = count_string + ' ' + document.title;
	};

	liveblog.enable_nag = function() {
		liveblog.nag_disabled = false;
	};

	liveblog.disable_nag = function() {
		liveblog.nag_disabled = true;
	};

	liveblog.is_nag_disabled = function() {
		return liveblog.nag_disabled;
	};

	liveblog.get_entry_by_id = function( id ) {
		return $( '#liveblog-entry-' + id );
	};

	liveblog.display_entry = function( new_entry ) {
		var $entry = liveblog.get_entry_by_id( new_entry.id );
		if ( $entry.length ) {
			liveblog.update_entry( $entry, new_entry );
		} else {
			liveblog.add_entry( new_entry );
		}
		$( document.body ).trigger( 'post-load' );
	};

	liveblog.add_entry = function( new_entry ) {
		var $new_entry = $( new_entry.html );
		$new_entry.addClass( 'liveblog-hidden' ).prependTo( liveblog.$entry_container );
	};

	liveblog.update_entry = function( $entry, updated_entry ) {
		var $updated_entry = $( updated_entry.html );
		var updated_text   = $( '.liveblog-entry-text', $updated_entry ).html();

		if ( updated_text ) {
			$( '.liveblog-entry-text', $entry ).html( updated_text );
		} else {
			liveblog.delete_entry( $entry );
		}
	};

	liveblog.delete_entry = function( $entry ) {
		$entry.remove();
	};

	liveblog.get_all_entries = function() {
		return liveblog.$entry_container.find( '.liveblog-entry' );
	};

	liveblog.get_hidden_entries = function() {
		return liveblog.get_all_entries().filter( '.liveblog-hidden' );
	};

	liveblog.get_visible_entries = function() {
		return liveblog.get_all_entries().not( '.liveblog-hidden' );
	};

	liveblog.unhide_entries = function() {
		liveblog.get_hidden_entries().addClass('highlight').removeClass( 'liveblog-hidden' ).animate({backgroundColor: 'white'}, {duration: 5000});
	};

	liveblog.ajax_request = function( url, data, success_callback, error_callback, method ) {
		if ( 'function' !== typeof( success_callback ) )
			success_callback = liveblog.success_callback;

		if ( 'function' !== typeof( error_callback ) )
			error_callback = liveblog.error_callback;

		method = method || 'GET';

		$.ajax( {
			url: url,
			data: data,
			type: method,
			dataType: 'json',
			success: success_callback,
			error: error_callback
		} );
	};

	liveblog.success_callback = function() {};
	liveblog.error_callback   = function() {};

	liveblog.add_error = function( response ) {
		alert( 'Error ' + response.status + ': ' + response.statusText );
	};

	liveblog.show_spinner = function() {
		liveblog.$spinner.spin( 'small' );
	};

	liveblog.hide_spinner = function() {
		liveblog.$spinner.spin( false );
	};

	liveblog.current_timestamp = function() {
		return Math.floor( Date.now() / 1000 );
	};

	liveblog.server_timestamp_from_xhr = function(xhr) {
		var timestamp_milliseconds = Date.parse( xhr.getResponseHeader( 'Date' ) );
		return Math.floor( timestamp_milliseconds / 1000 );
	};

	// Initialize everything!
	$( document ).ready( liveblog.init );

} )( jQuery );
