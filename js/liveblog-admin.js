/* global ajaxurl, liveblog_admin_settings, liveblog, _, jQuery, Backbone */
window.liveblog = window.liveblog || {};
( function( $ ) {
	liveblog.PostMetaBoxView = Backbone.View.extend( {
		el: '#liveblog',
		events: {
			'click button': 'clickButton'
		},
		clickButton: function( e ) {
			e.preventDefault();
			this.render( e.target.value );
		},
		render: function( state ) {
			var view = this,
				params = {
				action: 'set_liveblog_state_for_post',
				post_id: this.options.post_id,
				state: state
			};
			params[this.options.nonce_key] = this.options.nonce;
			this.$( '.inside' ).load( ajaxurl, params, function( response, status, xhr ) {
				if ( status === 'success') {
					return;
				}
				if (xhr.status && xhr.status > 200) {
					view.showError( xhr.statusText, xhr.status );
				} else {
					view.showError( status );
				}
			} );
		},
		showError: function( text, code ) {
			var template = code? liveblog_admin_settings.error_message_template : liveblog_admin_settings.short_error_message_template,
				message = template.replace( '{error-message}', text ).replace( '{error-code}', code );
			this.$( 'p.error' ).show().html( message );
		}
	});

	liveblog.postMetaBox = new liveblog.PostMetaBoxView( _.extend( liveblog_admin_settings, {post_id: $( '#post_ID' ).val() } ) );
} ( jQuery ) );
