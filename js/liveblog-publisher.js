/* global liveblog, liveblog_settings, liveblog_publisher_settings, _, confirm, jQuery, Backbone */
( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' ) {
		return;
	}

	_.templateSettings = {
		interpolate : /\{\{(.+?)\}\}/g
	};

	liveblog.InsertEntryView = Backbone.View.extend({
		tagName: 'div',
		className: 'liveblog-form',
		template: _.template($('#liveblog-form-template').html()),
		entry_tab_label: liveblog_publisher_settings.new_entry_tab_label,
		submit_label: liveblog_publisher_settings.new_entry_submit_label,
		is_rich_text_enabled: false,
		events: {
			'click .cancel': 'cancel',
			'keydown .liveblog-form-entry': 'entry_keyhandle_submit_cancel',
			'input .liveblog-form-entry': 'entry_inputhandler_textarea',
			'keydown .liveblog-form-rich-entry': 'entry_keyhandler_contenteditable',
			'input .liveblog-form-rich-entry': 'entry_inputhandler_contenteditable',
			'click .liveblog-html-edit-toggle input': 'toggled_rich_text',
			'click .liveblog-formatting-command': 'rich_formatting_btn_click',
			'mousedown .liveblog-formatting-command': 'rich_formatting_btn_mousedown_preventdefault',
			'click .liveblog-form-entry-submit': 'submit',
			'click li.entry a': 'tab_entry',
			'click li.preview a': 'tab_preview'
		},
		render: function() {
			this.render_template();
			this.$('.cancel').hide();
			this.$('.liveblog-entry-delete').hide();
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
			this.setup_rich_editing();
		},
		install_shortcuts_to_elements: function() {
			this.$textarea = this.$('.liveblog-form-entry');
			this.$richarea = this.$('.liveblog-rich-form-entry');
			this.$contenteditable = this.$('.liveblog-form-rich-entry');
			this.$html_edit_toggle = this.$('.liveblog-html-edit-toggle input');
			this.$submit_button = this.$('.liveblog-form-entry-submit');
			this.$spinner = this.$('.liveblog-submit-spinner');
		},
		setup_rich_editing: function () {
			this.is_rich_text_enabled = (
				// check if WordPress prevented rich text via liveblog_rich_text_editing_allowed filter
				this.$contenteditable.length > 0
				&&
				// check if browser supports contenteditable
				typeof this.$contenteditable[0].contentEditable !== 'undefined'
				&&
				typeof document.execCommand !== 'undefined'
			);
			if (this.is_rich_text_enabled) {
				this.$el.addClass('rich-text-enabled');
				this.toggled_rich_text();
			}
		},
		toggled_rich_text: function (e) {
			this.$textarea.toggle( this.$html_edit_toggle.prop('checked') );
			this.$richarea.toggle( !this.$html_edit_toggle.prop('checked') );
		},
		get_content_for_form: function() {
			return '';
		},
		submit: function(e) {
			e.preventDefault();
			this.crud('insert');
		},
		entry_keyhandle_submit_cancel: function (e) {
			var cmd_ctrl_key = (e.metaKey && !e.ctrlKey) || e.ctrlKey;

			// cmd/ctrl + enter
			if( cmd_ctrl_key && (e.keyCode === 10 || e.keyCode === 13) ) {
				e.preventDefault();
				this.$submit_button.click();
				return false;
			}

			// Escape Key
			if( e.keyCode === 27 ) {
				e.preventDefault();
				this.$('.cancel:visible').click();
				return false;
			}

			return true;
		},
		entry_inputhandler_textarea: function (e) {
			if (!this.is_rich_text_enabled) {
				return;
			}
			var html = this.$textarea.val();
			html = html.replace(/\n/g, '<br>');
			// @todo replace image URLs with <img> elements
			this.$contenteditable.html(html);
		},

		entry_keyhandler_contenteditable: function(e) {
			if ( ! this.entry_keyhandle_submit_cancel(e) ) {
				return false;
			}
			var cmd_ctrl_key = (e.metaKey && !e.ctrlKey) || e.ctrlKey;
			var key_command_map = {
				'b': 'bold',
				'i': 'italic',
				'u': 'underline',
				'k': 'createLink'
			};
			var char_code = String.fromCharCode(e.keyCode).toLowerCase();
			if (key_command_map[char_code]) {
				this.entry_command( key_command_map[char_code] );
				return false;
			}
			return true;
		},

		entry_inputhandler_contenteditable: function (e) {
			// Remove any straggling br (Chrome likes to leave one)
			this.$contenteditable.find('> br:only-child').filter(function() {
				// :only-child only considers elements, not text nodes before or after
				return !this.previousSibling && !this.nextSibling;
			}).remove();
			var text = this.$contenteditable.html();
			// @todo Replace <div>(.+?)</div> with $1\n
			// @todo Replace <p>(.+?)</p> with $1\n\n
			// @todo also should convert <img> elements into bare URLs?
			text = text.replace(/<br\s*\/?>/g, "\n");
			this.$textarea.val(text);
		},

		rich_formatting_btn_mousedown_preventdefault: function (e) {
			// Note: This is not needed if the toolbar button is a <button>
			e.preventDefault();
		},
		rich_formatting_btn_click: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var command = $btn.data('command');
			this.entry_command(command);
		},
		entry_command: function (command, value) {
			value = value || '';

			if (command === 'insertImage') {
				// @todo Localize
				// @todo if value is empty, prep-populate with selected image URL
				value = prompt( 'Provide URL to image:', value );
				if (value === null) {
					return;
				}
			}
			else if (command === 'createLink') {
				// @todo Localize
				// @todo if value is empty, prep-populate with selected link URL
				value = prompt( 'Provide URL for link:', value );
				if (value === null) {
					return;
				}
				if (value === '') {
					command = 'unlink';
				}
			}

			document.execCommand( command, false, value );
		},
		cancel: function(e) {
			e.preventDefault();
			this.$entry_text.show();
			this.$entry.find('.liveblog-entry-edit').show();
			this.$entry.find('.liveblog-entry-actions .liveblog-entry-delete').show();
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
			if (this.is_rich_text_enabled) {
				this.$contenteditable.attr( 'contenteditable', 'false' );
			}
		},
		enable: function() {
			this.$submit_button.attr( 'disabled', null);
			this.$textarea.attr( 'disabled', null);
			if (this.is_rich_text_enabled) {
				this.$contenteditable.attr( 'contenteditable', 'true' );
			}
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
			var new_entry_content = this.$textarea.val(),
				data = {
					crud_action: action,
					post_id: liveblog_settings.post_id,
					entry_id: this.get_id_for_ajax_request(),
					content: new_entry_content
				};
			if ( ! new_entry_content ) {
				return;
			}
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
		error: function(response, status) {
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
			if (!content) {
				return;
			}
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
		error: function(response, status) {
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
		var entry = $( e.target ).closest( '.liveblog-entry' ),
			id = entry.attr( 'id' ).replace( 'liveblog-entry-', '' ),
			form = new liveblog.EditEntryView({entry: entry});
		if ( !id ) {
			return;
		}
		form.render();
		entry.find( '.liveblog-entry-edit' ).hide();
		entry.find('.liveblog-entry-actions .liveblog-entry-delete').hide();
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
