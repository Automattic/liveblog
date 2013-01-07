( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' )
		return;

	liveblog.publisher = {};

	liveblog.publisher.init = function() {

		liveblog.publisher.$entry_text   = $( '#liveblog-form-entry'        );
		liveblog.publisher.$entry_button = $( '#liveblog-form-entry-submit' );
		liveblog.publisher.$nonce        = $( '#liveblog_nonce'             );
		liveblog.publisher.$spinner      = $( '#liveblog-submit-spinner'    );
		liveblog.publisher.$preview      = $( '#liveblog-preview' );
		liveblog.publisher.$tabs         = $( '#liveblog-tabs' );

		liveblog.publisher.$entry_button.click( liveblog.publisher.submit_click );
		$('#liveblog-entries').on( 'click', '.liveblog-entry-delete', liveblog.publisher.delete_click );
		$('#liveblog-entries').on( 'click', '.liveblog-entry-edit', liveblog.publisher.edit_click );

		liveblog.publisher.$tabs.tabs({select: liveblog.publisher.preview_select});
	};

	liveblog.publisher.submit_click = function( e ) {
		e.preventDefault();
        if ( !$( e.target).hasClass( 'edit-entry-submit' ) ) {
            liveblog.publisher.insert_entry();
        } else {
            var id = $( e.target ).closest( '.liveblog-entry' ).attr( 'id' ).replace( 'liveblog-entry-', '' );
            liveblog.publisher.update_entry( id );
        }

	};

	liveblog.publisher.preview_select = function( e, ui ) {
		if ( -1 == ui.tab.href.search( '#liveblog-preview' ) ) {
			return;
		}

		var entry_content = liveblog.publisher.$entry_text.val();
		if ( !entry_content ) {
			return;
		}

		var data = {
			action: 'liveblog_preview_entry',
			entry_content: entry_content
		};
		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();

		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.$preview.html('Loading preview…');
		liveblog.ajax_request( liveblog_settings.endpoint_url + 'preview', data, liveblog.publisher.preview_entry_success, liveblog.publisher.preview_entry_error, 'POST' );
	};

	liveblog.publisher.preview_entry_success = function( response ) {
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.$preview.html( '<div class="liveblog-entry"><div class="liveblog-entry-text">' + response.html + '</div></div>' );
		$( document.body ).trigger( 'post-load' );
	};

	liveblog.publisher.preview_entry_error = function( response, status ) {
		liveblog.add_error( response, status );
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

    liveblog.publisher.edit_click = function( e ) {
        e.preventDefault();
        var entry = $( e.target ).closest( '.liveblog-entry' );
        var entry_text = entry.children( '.liveblog-entry-text' ).html();
        entry_text = entry_text.trim();

        var id = entry.attr( 'id' ).replace( 'liveblog-entry-', '' );
        if ( !id ) {
            return;
        }

        entry.html( liveblog.publisher.$tabs.clone() );
        entry.find( '.liveblog-form-entry' ).val( entry_text );
        entry.find( '#liveblog-form-entry-submit').addClass( 'edit-entry-submit' );
        entry.find( '#liveblog-actions ul li:first-child a').text('Edit Entry');

    };

	liveblog.publisher.insert_entry = function() {
		var entry_content = liveblog.publisher.$entry_text.val();

		if ( ! entry_content )
			return;

		var data = {
			crud_action: 'insert',
			content: entry_content,
			post_id: liveblog_settings.post_id
		};

		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();
		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.show_spinner();
		liveblog.ajax_request( liveblog_settings.endpoint_url + 'crud', data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
	};

	liveblog.publisher.insert_entry_success = function( response, status, xhr ) {
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.hide_spinner();
		liveblog.publisher.$entry_text.val( '' );

		liveblog.reset_timer();
		liveblog.get_recent_entries_success( response, status, xhr );
	};

	liveblog.publisher.insert_entry_error = function( response, status, error ) {
		liveblog.add_error( response, status );
		liveblog.publisher.enable_posting_interface();
		liveblog.publisher.hide_spinner();
	};

	liveblog.publisher.delete_entry = function( id ) {
		var data = {
			crud_action: 'delete',
			post_id: liveblog_settings.post_id,
			entry_id: id,
		};
		data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();
		liveblog.publisher.disable_posting_interface();
		liveblog.publisher.show_spinner();
		liveblog.ajax_request( liveblog_settings.endpoint_url + 'crud', data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
	};

    liveblog.publisher.update_entry = function( id ) {
        var entry_content = liveblog.publisher.$entry_text.val();

        if ( ! entry_content )
            return;

        var data = {
            crud_action: 'update',
            post_id: liveblog_settings.post_id,
            entry_id: id,
            content: entry_content,
        };
        data[ liveblog_settings.nonce_key ] = liveblog.publisher.$nonce.val();
        liveblog.publisher.disable_posting_interface();
        liveblog.publisher.show_spinner();
        liveblog.ajax_request( liveblog_settings.endpoint_url + 'crud', data, liveblog.publisher.insert_entry_success, liveblog.publisher.insert_entry_error, 'POST' );
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
