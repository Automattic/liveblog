( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' )
		return;

	_.templateSettings = {
		interpolate : /\{\{(.+?)\}\}/g
	};

	liveblog.InsertEntryView = Backbone.View.extend({
		tagName: "div",
		className: "liveblog-form",
		template: _.template($('#liveblog-form-template').html()),
		entry_tab_label: 'New Entry',
		submit_label: 'Publish Update',
		events: {
			'click .cancel': 'cancel',
			'click .liveblog-form-entry-submit': 'submit',
		},
		render: function() {
			this.render_template();
			this.$('.cancel').hide();
			$('#liveblog-messages').after(this.$el);
  		},
		render_template: function() {
			this.$el.html(this.template({
				content: this.get_content_for_form(),
				entry_tab_label: this.entry_tab_label,
		   		submit_label: this.submit_label,
			}));
			this.install_shortcuts_to_elements();
  		},
		install_shortcuts_to_elements: function() {
			this.$textarea = this.$('.liveblog-form-entry');
			this.$submit_button = this.$('.liveblog-form-entry-submit');
			this.$spinner = this.$('.liveblog-submit-spinner');
		},
		get_content_for_form: function() {
			return '';
		},
		submit: function(e) {
			e.preventDefault();
			this.crud('insert');
		},
		cancel: function(e) {
			e.preventDefault();
			this.$entry_text.show()
			this.$entry.find('.liveblog-entry-edit').show();
			this.remove();
		},
		disable: function() {
			this.$submit_button.attr( 'disabled', 'disabled' );
			this.$textarea.attr( 'disabled', 'disabled' );
		},
		enable: function() {
			this.$submit_button.attr( 'disabled', null);
			this.$textarea.attr( 'disabled', null);
		},
		show_spinner: function() {
			this.$spinner.spin('small');
		},
		hide_spinner: function() {
			this.$spinner.spin(false);
		},
		get_id_for_ajax_request: function() {
			return null;
		},
		crud: function(action) {
			var new_entry_content = this.$textarea.val();
			if ( ! new_entry_content )
				return;
			var data = {
				crud_action: action,
				post_id: liveblog_settings.post_id,
				entry_id: this.get_id_for_ajax_request(),
				content: new_entry_content,
			};
			data[liveblog_settings.nonce_key] = liveblog.publisher.nonce;
			this.disable();
			this.show_spinner();
			liveblog.ajax_request( liveblog_settings.endpoint_url + 'crud', data, _.bind(this.success, this), _.bind(this.error, this), 'POST' );
		},
		success: function(response, status, xhr) {
			this.enable();
			this.hide_spinner();
			this.$textarea.val('');
			liveblog.reset_timer();
			liveblog.get_recent_entries_success(response, status, xhr);
		},
		error: function(response, status, error) {
			liveblog.add_error(response, status);
			this.enable();
			this.hide_spinner();
		}
	});

	liveblog.EditEntryView = liveblog.InsertEntryView.extend({
		entry_tab_label: 'Edit Entry',
		submit_label: 'Update',
		initialize: function(options) {
			this.$entry = options.entry;
			this.$entry_text = this.$entry.find('.liveblog-entry-text');
		},
		get_content_for_form: function() {
			return this.$entry_text.data('original-content');
		},
		get_id_for_ajax_request: function() {
			 return this.$entry.attr('id').replace('liveblog-entry-', '');
		},
		render: function() {
			this.render_template();
			this.$entry_text.hide().after(this.$el);
			this.$('.liveblog-form-entry').focus();
			return this;
		},
		submit: function(e) {
			e.preventDefault();
			this.crud('update');
		},
	});

	liveblog.publisher = {};

	liveblog.publisher.init = function() {
		liveblog.insert_form = new liveblog.InsertEntryView();
		liveblog.insert_form.render();
		liveblog.publisher.nonce         = liveblog_settings.nonce;
		liveblog.publisher.$entry_text   = $( '.liveblog-form-entry'        );
		liveblog.publisher.$entry_button = $( '.liveblog-form-entry-submit' );
		liveblog.publisher.$spinner      = $( '.liveblog-submit-spinner'    );
		liveblog.publisher.$preview      = $( '#liveblog-preview' );

		$('#liveblog-entries').on( 'click', '.liveblog-entry-delete', liveblog.publisher.delete_click );
		$('#liveblog-entries').on( 'click', '.liveblog-entry-edit', liveblog.publisher.edit_click );
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
		data[ liveblog_settings.nonce_key ] = liveblog.publisher.nonce;

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
		var id = entry.attr( 'id' ).replace( 'liveblog-entry-', '' );
		if ( !id ) {
			return;
		}
		var form = new liveblog.EditEntryView({entry: entry});
		form.render();
		entry.find( '.liveblog-entry-edit' ).hide();
	};

	liveblog.publisher.delete_entry = function( id ) {
		var data = {
			crud_action: 'delete',
			post_id: liveblog_settings.post_id,
			entry_id: id,
		};
		data[liveblog_settings.nonce_key] = liveblog.publisher.nonce;
		liveblog.insert_form.disable();
		liveblog.insert_form.show_spinner();
		liveblog.ajax_request( liveblog_settings.endpoint_url + 'crud', data, _.bind(liveblog.insert_form.success, liveblog.insert_form), _.bind(liveblog.insert_form.error, liveblog.insert_form), 'POST' );
	};

	liveblog.$events.bind( 'after-init', liveblog.publisher.init );
} )( jQuery );
