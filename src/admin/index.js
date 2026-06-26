import './styles.scss';

/* global ajaxurl, liveblog_admin_settings, jQuery */
jQuery( function ( $ ) {
	const $meta_box = $( '#liveblog' ),
		post_id = $( '#post_ID' ).val(),
		show_error = function ( status, code ) {
			const template = code
					? liveblog_admin_settings.error_message_template
					: liveblog_admin_settings.short_error_message_template,
				message = template
					.replace( '{error-message}', status )
					.replace( '{error-code}', code );
			$( 'p.error', $meta_box ).show().html( message );
		};
	$meta_box.on( 'click', 'button', function ( e ) {
		e.preventDefault();
		const data = {};
		let url, method;

		if ( liveblog_admin_settings.use_rest_api === '1' ) {
			url = liveblog_admin_settings.endpoint_url;
			data.state = encodeURIComponent( $( this ).val() );
			data.template_name = encodeURIComponent(
				$( '#liveblog-key-template-name' ).val()
			);
			data.template_format = encodeURIComponent(
				$( '#liveblog-key-template-format' ).val()
			);
			data.limit = encodeURIComponent( $( '#liveblog-key-limit' ).val() );
			data[ liveblog_admin_settings.nonce_key ] =
				liveblog_admin_settings.nonce;
			method = 'POST';
		} else {
			url =
				ajaxurl +
				'?action=set_liveblog_state_for_post&post_id=' +
				encodeURIComponent( post_id ) +
				'&state=' +
				encodeURIComponent( $( this ).val() ) +
				'&' +
				liveblog_admin_settings.nonce_key +
				'=' +
				liveblog_admin_settings.nonce;
			url += '&' + $( 'input, textarea, select', $meta_box ).serialize();
			method = 'GET';
		}

		$.ajax( url, {
			dataType: 'json',
			data,
			method,
			success( response, status ) {
				// Replace the metabox
				$( '.inside', $meta_box ).empty().append( response );

				if ( status === 'success' ) {
					$( 'p.success', $meta_box )
						.show( 0 )
						.delay( 5000 )
						.hide( 0 );
				}
			},
			error( xhr, status ) {
				if ( xhr.status && xhr.status > 200 ) {
					show_error( xhr.statusText, xhr.status );
				} else {
					show_error( status );
				}
			},
		} );
	} );
} );
