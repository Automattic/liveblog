( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' )
		return;

	liveblog.publisher = {}

	// TODO: fire after liveblog.init
	liveblog.publisher.init = function() {
		liveblog.disable_nag();

		liveblog.publisher.$entry_text = $( '#liveblog-form-entry' );
		liveblog.publisher.$entry_button = $( '#liveblog-form-entry-submit' );
		liveblog.publisher.$nonce = $( '#liveblog_nonce' );
		liveblog.publisher.$entry_button.bind( 'click', liveblog.publisher.submit_click );
	}
	liveblog.publisher.submit_click = function( e ) {
		e.preventDefault();
		liveblog.publisher.insert_entry();
	}

	liveblog.publisher.insert_entry = function() {
		var entry_content = liveblog.publisher.$entry_text.val();

		if ( ! entry_content )
			return;

		var data = {
			action: 'liveblog_insert_entry',
			entry_content: entry_content,
			post_id: liveblog_settings.post_id
		}

		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();

		liveblog.ajax_request( liveblog_settings.ajaxurl, data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
	}
	liveblog.publisher.insert_entry_success = function( data ) {
		liveblog.publisher.$entry_text.val( '' );

		liveblog.reset_timer();
		liveblog.get_recent_entries();

	}
	liveblog.publisher.insert_entry_error = function( data ) {
		
	}
	
	$( document ).ready( liveblog.publisher.init );
} )( jQuery );