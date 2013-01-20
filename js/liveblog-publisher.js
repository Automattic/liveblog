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
		entry_tab_label: liveblog_publisher_settings.new_entry_tab_label,
		submit_label: liveblog_publisher_settings.new_entry_submit_label,
		events: {
			'click .cancel': 'cancel',
			'click .liveblog-form-entry-submit': 'submit',
			'click li.entry a': 'tab_entry',
			'click li.preview a': 'tab_preview'
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
				submit_label: this.submit_label
			}));
			this.install_shortcuts_to_elements();
			this.preview = new liveblog.PreviewView({form: this, el: this.$('.liveblog-preview')});
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
			this.$entry_text.show();
			this.$entry.find('.liveblog-entry-edit').show();
			this.remove();
		},
		tab_entry: function(e) {
			e.preventDefault();
			this.switch_to_entry();
		},
		tab_preview: function(e) {
			e.preventDefault();
			this.switch_to_preview();
			this.preview.render(this.$textarea.val());
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
				content: new_entry_content
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
		},
		switch_to_preview: function() {
			this.$('li.preview').addClass('active');
			this.$('li.entry').removeClass('active');
			this.$('.liveblog-edit-entry').hide();
			this.preview.show();
		},
		switch_to_entry: function() {
			this.preview.hide();
			this.$('li.preview').removeClass('active');
			this.$('li.entry').addClass('active');
			this.$('.liveblog-edit-entry').show();
		}
	});

	liveblog.EditEntryView = liveblog.InsertEntryView.extend({
		entry_tab_label: liveblog_publisher_settings.edit_entry_tab_label,
		submit_label: liveblog_publisher_settings.edit_entry_submit_label,
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
		}
	});

	liveblog.PreviewView = Backbone.View.extend({
		initialize: function(options) {
			this.form = options.form;
		},
		render: function(content) {
			if (!content) return;
			var data = {
				entry_content: content
			};
			data[liveblog_settings.nonce_key] = liveblog.publisher.nonce;
			this.form.disable();
			this.$el.html(liveblog_publisher_settings.loading_preview);
			liveblog.ajax_request( liveblog_settings.endpoint_url + 'preview', data, _.bind(this.success, this), _.bind(this.error, this), 'POST' );
		},
		success: function(response) {
			this.form.enable();
			this.$el.html( '<div class="liveblog-entry"><div class="liveblog-entry-text">' + response.html + '</div></div>' );
			$( document.body ).trigger( 'post-load' );
		},
		error: function() {
			liveblog.add_error( response, status );
			this.form.enable();
			this.form.switch_to_entry();
		},
		show: function() {
			this.$el.show();
		},
		hide: function() {
			this.$el.hide();
		}
	});

	liveblog.publisher = {};

	liveblog.publisher.init = function() {
		liveblog.publisher.insert_form = new liveblog.InsertEntryView();
		liveblog.publisher.insert_form.render();
		liveblog.publisher.nonce = liveblog_settings.nonce;

		$('#liveblog-entries').on( 'click', '.liveblog-entry-delete', liveblog.publisher.delete_click );
		$('#liveblog-entries').on( 'click', '.liveblog-entry-edit', liveblog.publisher.edit_click );
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
			entry_id: id
		};
		data[liveblog_settings.nonce_key] = liveblog.publisher.nonce;
		liveblog.publisher.insert_form.disable();
		liveblog.publisher.insert_form.show_spinner();
		liveblog.ajax_request( liveblog_settings.endpoint_url + 'crud', data, _.bind(liveblog.publisher.insert_form.success, liveblog.publisher.insert_form), _.bind(liveblog.publisher.insert_form.error, liveblog.publisher.insert_form), 'POST' );
	};

	liveblog.$events.bind( 'after-init', liveblog.publisher.init );
} )( jQuery );
