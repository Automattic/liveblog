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
		var data = {};

		if (liveblog_admin_settings.use_rest_api == 1) {
			var url = liveblog_admin_settings.endpoint_url;
			data['state']                           = encodeURIComponent( $( this ).val() );
			data['template_name']                   = encodeURIComponent( $( '#liveblog-key-template-name' ).val() );
			data['template_format']                 = encodeURIComponent( $( '#liveblog-key-template-format' ).val() );
			data['limit']                           = encodeURIComponent( $( '#liveblog-key-limit' ).val() );
			data[liveblog_admin_settings.nonce_key] = liveblog_admin_settings.nonce;
			var method = 'POST';

		} else {
			var url    = ajaxurl + '?action=set_liveblog_state_for_post&post_id=' + encodeURIComponent( post_id ) + '&state=' + encodeURIComponent( $( this ).val() ) + '&' + liveblog_admin_settings.nonce_key + '=' + liveblog_admin_settings.nonce;
			url       += '&' + $('input, textarea, select', $meta_box).serialize();
			var method = 'GET';
		}
		
		$.ajax( url, {
			dataType: 'json',
			data: data,
			method: method,
			success: function( response, status, xhr ) {
				// Replace the metabox
				$( '.inside', $meta_box ).empty().append( response );

				if ( status === 'success') {
					$( 'p.success', $meta_box ).show(0).delay( 1000 ).hide(0);
					return;
				}
			},
			error:  function( xhr, status, error ) {
				if (xhr.status && xhr.status > 200) {
					show_error( xhr.statusText, xhr.status );
				} else {
					show_error( status );
				}
			}
		} );
	} );
} );