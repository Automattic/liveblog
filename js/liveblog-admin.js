/* global ajaxurl, liveblog_admin_settings, jQuery */
jQuery( function( $ ) {
	var $meta_box = $( '#liveblog' ),
		post_id = $( '#post_ID' ).val(),
		show_error = function( status, code ) {
			var template = code? liveblog_admin_settings.error_message_template : liveblog_admin_settings.short_error_message_template,
				message = template.replace( '{error-message}', status ).replace( '{error-code}', code );
			$( 'p.error', $meta_box ).show().html( message );
		};
	$meta_box.on( 'click', 'button', function( e ) {
		e.preventDefault();
		var url = ajaxurl + '?action=set_liveblog_state_for_post&post_id=' + encodeURIComponent( post_id ) + '&state=' + encodeURIComponent( $( this ).val() ) + '&' + liveblog_admin_settings.nonce_key + '=' + liveblog_admin_settings.nonce;
		$( '.inside', $meta_box ).load( url, function( response, status, xhr ) {
			if ( status === 'success') {
				return;
			}
			if (xhr.status && xhr.status > 200) {
				show_error( xhr.statusText, xhr.status );
			} else {
				show_error( status );
			}
		} );
	} );
} );
