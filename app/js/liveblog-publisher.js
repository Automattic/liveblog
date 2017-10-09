/* global liveblog, liveblog_settings, liveblog_publisher_settings, _, confirm, jQuery, Backbone, switchEditors, prompt */
( function( $ ) {
	if ( typeof( liveblog ) === 'undefined' ) {
		return;
	}

	var isFirefox = typeof InstallTrigger !== 'undefined',
		isIE      = /*@cc_on!@*/false || !!document.documentMode,
		isEdge    = !isIE && !!window.StyleMedia,
		isChrome  = !!window.chrome && !!window.chrome.webstore,
		isSafari  = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0 || (function (p) { return p.toString() === "[object SafariRemoteNotification]"; })(!window['safari'] || safari.pushNotification);

	liveblog.InsertEntryView = Backbone.View.extend({
		tagName: 'div',
		className: 'liveblog-form',
		template: _.template($('#liveblog-form-template').html(), null, { interpolate : /\{\{(.+?)\}\}/g }),
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
			'click li.preview a': 'tab_preview',
			'dragenter .liveblog-form-rich-entry': 'update_contenteditable_before_drop',
		},
		render: function() {
			this.render_template();
			this.$('.cancel').hide();
			this.$('.liveblog-entry-delete').hide();
			$('#liveblog-messages').after(this.$el);
			liveblog.publisher.autocomplete(this.$contenteditable);
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
				this.$contenteditable.length > 0 &&
				// check if browser supports contenteditable
				typeof this.$contenteditable[0].contentEditable !== 'undefined' &&
				typeof document.execCommand !== 'undefined' &&
				('oninput' in document.createElement('input')) // MSIE<=8
			);
			if (this.is_rich_text_enabled) {
				this.entry_inputhandler_textarea();
				this.$el.addClass('rich-text-enabled');
				this.toggled_rich_text();
			}
			else {
				var noop = function () {};
				this.toggled_rich_text = noop;
				this.entry_inputhandler_textarea = noop;
				this.entry_keyhandler_contenteditable = noop;
				this.rich_formatting_btn_click = noop;
				this.rich_formatting_btn_mousedown_preventdefault = noop;
			}
		},
		toggled_rich_text: function () {
			this.$contenteditable.trigger('input');
			var is_html_mode = this.$html_edit_toggle.prop('checked');
			this.$textarea.toggle( is_html_mode );
			this.$richarea.toggle( !is_html_mode );
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

		/**
		 * input event handler for textarea
		 * Convert oEmbedish image URLs into <img> elements, and do wpautop(ish)
		 */
		entry_inputhandler_textarea: function () {
			var html = this.$textarea.val();

			html = html.replace(/(^|\n)(https?:\/\/\S+?\.(?:png|gif|jpe?g))($|\n)/ig, '$1<img src="$2">$3');
			html = switchEditors.wpautop(html);

			this.$contenteditable.html(html);
		},

		/**
		 * keydown event handler for contenteditable
		 * Handle the keyboard shortcuts for submit, cancel, and formatting
		 */
		entry_keyhandler_contenteditable: function(e) {
			var self, command_key_map, char_code, cmd_ctrl_key, found_command;
			self = this;
			if ( ! this.entry_keyhandle_submit_cancel(e) ) {
				return false;
			}
			cmd_ctrl_key = (e.metaKey && !e.ctrlKey) || e.ctrlKey;
			char_code = String.fromCharCode(e.keyCode).toLowerCase();
			command_key_map = {
				'bold': function () {
					return cmd_ctrl_key && char_code === 'b';
				},
				'italic': function () {
					return cmd_ctrl_key && char_code === 'i';
				},
				'underline': function () {
					return cmd_ctrl_key && char_code === 'u';
				},
				'strikeThrough': function () {
					return cmd_ctrl_key && char_code === 's';
				},
				'createLink': function () {
					return cmd_ctrl_key && char_code === 'k';
				},
				'unlink': function () {
					return cmd_ctrl_key && char_code === 'l';
				},
				'removeFormat': function () {
					return cmd_ctrl_key && e.keyCode === 220  /* backslash */;
				},
				'lineBreak': function() {
					return e.shiftKey && e.keyCode === 13;
				},
				'paragraphBreak': function() {
					return e.keyCode === 13;
				}
			};
			found_command = false;
			$.each(command_key_map, function (command, test) {
				if (test.call()) {
					self.entry_command(command);
					found_command = true;
				}
				return !found_command;
			});
			return !found_command;
		},

		/**
		 * input event handler for contenteditble area, populates textarea value with HTML
		 * normalized and transformed (e.g. un-wpautop'ed) for saving and  editing in HTML mode
		 */
		entry_inputhandler_contenteditable: function () {
			var text = this.$contenteditable.html();
			text = switchEditors.pre_wpautop(text);
			this.$textarea.val(text);
		},

		/**
		 * Prevent defailt event when clicking on a rich formatting button so that selection
		 * is not lost in the contenteditable (not needed if toolbar buttons were <button>s)
		 */
		rich_formatting_btn_mousedown_preventdefault: function (e) {
			e.preventDefault();
		},

		/**
		 * click event handler for a formatting command button
		 */
		rich_formatting_btn_click: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget),
				command = $btn.data('command');
			this.entry_command(command);
		},

		/**
		 * Pass along formatting command with argument to document.execCommand
		 */
		entry_command: function (command, value) {
			value = value || '';

			if (command === 'createLink') {
				value = prompt( liveblog_settings.create_link_prompt, value );
				if (value === null) {
					return;
				}
				if (value === '') {
					command = 'unlink';
				}
			}
			if (command === 'lineBreak') {
				if ( isChrome || isSafari ) {
					command = 'insertHTML';
					value   = "<br><br>";
				} else {
					command = 'insertText';
					value   = "\n";
				}
			}
			if (command === 'paragraphBreak') {
				if ( isChrome || isSafari ) {
					command = 'insertHTML';
					value   = '<br><br><br>';
				} else {
					document.execCommand( 'insertText', false, "\n" );
					command = 'insertHTML';
					value   = '<br>';
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
			this.$contenteditable.trigger('input');
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
			this.$contenteditable.trigger('input');
			var new_entry_content = this.$textarea.val().trim(),
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
			this.$textarea.val('').trigger('input');

			if ( ! liveblog_settings.socketio_enabled ) {
				liveblog.reset_timer();
				liveblog.get_recent_entries_success(response, status, xhr);
			}
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
		},
		update_contenteditable_before_drop: function () {
			this.$contenteditable.trigger('input');
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
			if (this.is_rich_text_enabled) {
				this.$contenteditable.focus();
			}
			else {
				this.$textarea.focus();
			}
			liveblog.publisher.autocomplete(this.$contenteditable);
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
		$('.liveblog-key-entries').on( 'click', '.liveblog-key-event-delete', liveblog.publisher.delete_key );
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

	liveblog.publisher.delete_key = function( e ) {
		e.preventDefault();
		if ( !confirm( liveblog_settings.delete_key_confirm ) ) {
			return;
		}
		id = $(this).data('entry-id');
		content = $('#liveblog-entry-'+id+' .liveblog-entry-text').data('original-content').replace('<span class="liveblog-command type-key">key</span>', '');
		var data = {
			crud_action: 'delete_key',
			post_id: liveblog_settings.post_id,
			entry_id: id,
			content: content
		};
		data[liveblog_settings.nonce_key] = liveblog.publisher.nonce;
		liveblog.publisher.insert_form.disable();
		liveblog.publisher.insert_form.show_spinner();
		liveblog.ajax_request( liveblog_settings.endpoint_url + 'crud', data, _.bind(liveblog.publisher.insert_form.success, liveblog.publisher.insert_form), _.bind(liveblog.publisher.insert_form.error, liveblog.publisher.insert_form), 'POST' );
		liveblog.delete_entry($('.liveblog-key-events li.liveblog-entry-class-'+id));
	}

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

	/**
	 * Build autocomplete, called by both EditEntryView
	 * and InsertEntryView renders.
	 *
	 * @param elm
	 */
	liveblog.publisher.autocomplete = function( elm ) {
		var do_replacement = function (term, matches, out) {
			_.each(matches, function (match) {
				var key = match.substr(2, match.length - 3);

				if (key === 'term') {
					out = out.replace(match, term);

					return;
				}

				out = out.replace(match, term[key]);
			});

			return out;
		};

		elm.textcomplete(_.map(liveblog_settings.autocomplete, function (conf) {
			var template;
			if (conf.template != null) {
				template = function (term) {
					var out = conf.template;
					var matches = conf.template.match(/\$\{\w*\}/gi);

					return do_replacement(term, matches, out);
				};
			}

			switch (conf.type) {
				case 'static':
					return {
						terms: conf.data,
						match: new RegExp(conf.regex, 'i'),
						search: function (term, callback) {
							callback($.map(this.terms, function (_term) {
								var search = _term;

								if (conf.search != null) {
									search = '' + search[conf.search];
								}

								return search.indexOf(term) === 0 ? _term : null;
							}));
						},
						template: template,
						index: 1,
						replace: function (term) {
							var out = conf.replacement;
							var matches = conf.replacement.match(/\$\{\w*\}/gi);

							return do_replacement(term, matches, out) + '\u00A0';
						}
					};
				case 'ajax':
					return {
						cache: {},
						match: new RegExp(conf.regex, 'i'),
						search: function (term, callback) {
							if (conf.cache != null && this.cache[term] != null && this.cache[term].time < Date.now() - conf.cache) {
								return this.cache[term].data;
							}

							var _url  = conf.url;
							var _data = { autocomplete: term };

							if (liveblog_settings.use_rest_api == 1) {
								// Use the new REST API
								_url  = _url + term;
								_data = null;
							}

							var self = this;
							$.ajax({
								url: _url,
								data: _data,
								success: function (data) {
									self.cache[term] = {
										time: Date.now(),
										data: data
									};

									callback(data);
								},
								error: function () {
									callback([]);
								},
								dataType: 'json'
							});
						},
						template: template,
						index: 1,
						replace: function (term) {
							var out = conf.replacement;
							var matches = conf.replacement.match(/\$\{\w*\}/gi);

							return do_replacement(term, matches, out) + '\u00A0';
						}
					};
			}

			return null;
		}));
	};

	liveblog.$events.bind( 'after-init', liveblog.publisher.init );
} )( jQuery );
