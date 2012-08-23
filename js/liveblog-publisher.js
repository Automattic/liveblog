( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' )
		return;

	liveblog.publisher = {};

	liveblog.publisher.init = function() {
		liveblog.disable_nag();

		liveblog.publisher.$entry_text   = $( '#liveblog-form-entry'        );
		liveblog.publisher.$entry_button = $( '#liveblog-form-entry-submit' );
		liveblog.publisher.$nonce        = $( '#liveblog_nonce'             );
		liveblog.publisher.$spinner      = $( '#liveblog-submit-spinner'    );
		liveblog.publisher.$preview      = $( '#liveblog-preview' );
		liveblog.publisher.$tabs         = $( '#liveblog-tabs' );

		liveblog.publisher.$entry_button.click( liveblog.publisher.submit_click );
		$('#liveblog-entries').on( 'click', '.liveblog-entry-delete', liveblog.publisher.delete_click );

		liveblog.publisher.$tabs.tabs({select: liveblog.publisher.preview_select});
	};

	liveblog.publisher.submit_click = function( e ) {
		e.preventDefault();
		liveblog.publisher.insert_entry();
	};

	liveblog.publisher.preview_select = function( e, ui ) {
		if (1 != ui.index) {
			return;
		}

		var entry_content = liveblog.publisher.$entry_text.val();
		if ( !entry_content ) {
			return false;
		}

		var data = {
			action: 'liveblog_preview_entry',
			entry_content: entry_content
		};
		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();

		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.$preview.html('Loading preview…');
		liveblog.ajax_request( liveblog_settings.endpoint_url + '/preview', data, liveblog.publisher.preview_entry_success, liveblog.publisher.preview_entry_error, 'POST' );
	};

	liveblog.publisher.preview_entry_success = function( response ) {
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.$preview.html( '<div class="liveblog-entry"><div class="liveblog-entry-text">' + response.html + '</div></div>' );
		$( document.body ).trigger( 'post-load' );
	};

	liveblog.publisher.preview_entry_error = function( response ) {
		liveblog.add_error( response );
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.$tabs.tabs( 'select', 0 );
	};

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
	};

	liveblog.publisher.insert_entry = function() {
		var entry_content = liveblog.publisher.$entry_text.val();

		if ( ! entry_content )
			return;

		var data = {
			action: 'liveblog_insert_entry',
			entry_content: entry_content,
			post_id: liveblog_settings.post_id
		};

		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();
		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.show_spinner();
		liveblog.ajax_request( liveblog_settings.endpoint_url + '/insert', data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
	};

	liveblog.publisher.insert_entry_success = function( response, status, xhr ) {
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.hide_spinner();
		liveblog.publisher.$entry_text.val( '' );

		liveblog.reset_timer();
		liveblog.get_recent_entries_success( response, status, xhr );
	};

	liveblog.publisher.insert_entry_error = function( response ) {
		liveblog.add_error( response );
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.hide_spinner();
	};

	liveblog.publisher.delete_entry = function( id ) {
		var data = {
			action: 'liveblog_insert_entry',
			post_id: liveblog_settings.post_id,
			replaces: id,
			entry_content: ''
		};
		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();
		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.show_spinner();
		liveblog.ajax_request( liveblog_settings.endpoint_url + '/insert', data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
	};

	liveblog.publisher.disable_posting_interface = function() {
		liveblog.publisher.$entry_button.attr( 'disabled', 'disabled' );
		liveblog.publisher.$entry_text.attr( 'disabled', 'disabled' );
	};

	liveblog.publisher.enable_posting_interface = function() {
		liveblog.publisher.$entry_button.attr( 'disabled', null );
		liveblog.publisher.$entry_text.attr( 'disabled', null );
	};

	liveblog.publisher.show_spinner = function() {
		liveblog.publisher.$spinner.spin( 'small' );
	};

	liveblog.publisher.hide_spinner = function() {
		liveblog.publisher.$spinner.spin( false );
	};

	liveblog.$events.bind( 'after-init', liveblog.publisher.init );
} )( jQuery );
