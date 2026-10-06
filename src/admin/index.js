import './styles.scss';

/* global ajaxurl, jQuery */
jQuery( function ( $ ) {
	const settings = window.liveblog_admin_settings;
	const $metaBox = $( '#liveblog' ),
		postId = $( '#post_ID' ).val(),
		showError = function ( status, code ) {
			const template = code
					? settings.error_message_template
					: settings.short_error_message_template,
				message = template
					.replace( '{error-message}', status )
					.replace( '{error-code}', code );
			$( 'p.error', $metaBox ).show().html( message );
		};
	$metaBox.on( 'click', 'button', function ( e ) {
		e.preventDefault();
		const data = {};
		let url, method;

		if ( settings.use_rest_api === '1' ) {
			url = settings.endpoint_url;
			data.state = encodeURIComponent( $( this ).val() );
			data.template_name = encodeURIComponent(
				$( '#liveblog-key-template-name' ).val()
			);
			data.template_format = encodeURIComponent(
				$( '#liveblog-key-template-format' ).val()
			);
			data.limit = encodeURIComponent( $( '#liveblog-key-limit' ).val() );
			data[ settings.nonce_key ] = settings.nonce;
			method = 'POST';
		} else {
			url =
				ajaxurl +
				'?action=set_liveblog_state_for_post&post_id=' +
				encodeURIComponent( postId ) +
				'&state=' +
				encodeURIComponent( $( this ).val() ) +
				'&' +
				settings.nonce_key +
				'=' +
				settings.nonce;
			url += '&' + $( 'input, textarea, select', $metaBox ).serialize();
			method = 'GET';
		}

		$.ajax( url, {
			dataType: 'json',
			data,
			method,
			success( response, status ) {
				// Replace the metabox
				$( '.inside', $metaBox ).empty().append( response );

				if ( status === 'success' ) {
					$( 'p.success', $metaBox )
						.show( 0 )
						.delay( 5000 )
						.hide( 0 );
				}
			},
			error( xhr, status ) {
				if ( xhr.status && xhr.status > 200 ) {
					showError( xhr.statusText, xhr.status );
				} else {
					showError( status );
				}
			},
		} );
	} );
} );
