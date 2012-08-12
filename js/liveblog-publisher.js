( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' )
		return;

	liveblog.publisher = {}

	// TODO: fire after liveblog.init
	liveblog.publisher.init = function() {
		liveblog.disable_nag();

		liveblog.publisher.$entry_text   = $( '#liveblog-form-entry'        );
		liveblog.publisher.$entry_button = $( '#liveblog-form-entry-submit' );
		liveblog.publisher.$nonce        = $( '#liveblog_nonce'             );
		liveblog.publisher.$spinner      = $( '#liveblog-submit-spinner'    );

		liveblog.publisher.$entry_button.click( liveblog.publisher.submit_click );
		$( '.liveblog-entry-delete' ).click( liveblog.publisher.delete_click );
	}

	liveblog.publisher.submit_click = function( e ) {
		e.preventDefault();
		liveblog.publisher.insert_entry();
	}

	liveblog.publisher.delete_click = function( e ) {
		e.preventDefault();
		var id = $( e.target ).closest( '.liveblog-entry' ).attr( 'id' ).replace( 'liveblog-entry-', '' );
		if ( !id ) {
			return;
		}
		if ( !confirm( liveblog_settings.delete_confirmation ) ) {
			return;
		}
		liveblog.publisher.delete_entry( id );
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
		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.show_spinner();
		liveblog.ajax_request( liveblog_settings.ajaxurl, data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
	}

	liveblog.publisher.insert_entry_success = function( response ) {
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.hide_spinner();
		liveblog.publisher.$entry_text.val( '' );

		liveblog.reset_timer();
		liveblog.get_recent_entries_success( response );
	}

	liveblog.publisher.insert_entry_error = function( response ) {
		liveblog.add_error( response );
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.hide_spinner();
	}

	liveblog.publisher.delete_entry = function( id ) {
		var data = {
			action: 'liveblog_insert_entry',
			post_id: liveblog_settings.post_id,
			replaces: id,
			entry_content: ''
		}
		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();
		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.show_spinner();
		liveblog.ajax_request( liveblog_settings.ajaxurl, data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
 	}

	liveblog.publisher.disable_posting_interface = function() {
		liveblog.publisher.$entry_button.attr( 'disabled', 'disabled' );
		liveblog.publisher.$entry_text.attr( 'disabled', 'disabled' );
	}

	liveblog.publisher.enable_posting_interface = function() {
		liveblog.publisher.$entry_button.attr( 'disabled', null );
		liveblog.publisher.$entry_text.attr( 'disabled', null );
	}

	liveblog.publisher.show_spinner = function() {
		liveblog.publisher.$spinner.spin( 'small' );
	}

	liveblog.publisher.hide_spinner = function() {
		liveblog.publisher.$spinner.spin( false );
	}

	$( document ).ready( liveblog.publisher.init );
} )( jQuery );
