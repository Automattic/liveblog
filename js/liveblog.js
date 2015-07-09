/* global liveblog, liveblog_settings, _, alert, jQuery, moment, momentLang, Backbone */
window.liveblog = window.liveblog || {};

( function( $ ) {
	liveblog.EntriesView = Backbone.View.extend({
		el: '#liveblog-container',
		initialize: function() {
			liveblog.queue.on('reset', this.scrollToTop, this);
			$(window).scroll($.throttle(250, this.flushQueueWhenOnTop));
		},
		scrollToTop: function() {
			$(window).scrollTop(this.$el.offset().top);
		},
		flushQueueWhenOnTop: function() {
			if (liveblog.is_at_the_top()) {
				liveblog.queue.flush();
			}
		},
		updateTimes: function() {
			var self = this;
			this.$('.liveblog-entry').each(function() {
				var $entry = $(this),
					timestamp = $entry.data('timestamp'),
					human = self.formatTimestamp(timestamp);
				$('.liveblog-meta-time a', $entry).text(human);
			});
		},
		formatTimestamp: function(timestamp) {
			return moment.unix(timestamp).fromNow();
		}
	});

	liveblog.Entry = Backbone.Model.extend({});

	liveblog.EntriesQueue = Backbone.Collection.extend({
		model: liveblog.Entry,
		flush: function() {
			if (this.isEmpty()) {
				return;
			}
			liveblog.display_entries(this.models);
			this.reset([]);
		},
		applyModifyingEntries: function(entries) {
			var collection = this;
			_.each(entries, function(entry) {
				collection.applyModifyingEntry(entry);
			});
		},
		applyModifyingEntry: function(modifying) {
			var existing = this.get(modifying.id);
			if (!existing) {
				return;
			}
			if ('delete' === modifying.type) {
				this.remove(existing);
			}
			if ('update' === modifying.type) {
				existing.set('html', modifying.html);
			}
		}
	});

	liveblog.FixedNagView = Backbone.View.extend({
		el: '#liveblog-fixed-nag',
		events: {
			'click a': 'flush'
		},
		initialize: function() {
			liveblog.queue.on('all', this.render, this);
		},
		render: function() {
			var entries_in_queue = liveblog.queue.length;
			if ( entries_in_queue ) {
				this.show();
				this.updateNumber(liveblog.queue.length);
			} else {
				this.hide();
			}
		},
		show: function() {
			this.$el.show();
			this._moveBelowAdminBar();
		},
		hide: function() {
			this.$el.hide();
		},
		flush: function(e) {
			e.preventDefault();
			liveblog.queue.flush();
		},
		updateNumber: function(number) {
			var template = number === 1? liveblog_settings.new_update : liveblog_settings.new_updates,
				html = template.replace('{number}', '<span class="num">' + number + '</span>');
			this.$('a').html(html);
		},
		_moveBelowAdminBar: function() {
			var $adminbar = $('#wpadminbar');
			if ($adminbar.length) {
				this.$el.css('top', $adminbar.height());
			}
		}
	});

	liveblog.TitleBarCountView = Backbone.View.extend({
		initialize: function() {
			liveblog.queue.on('all', this.render, this);
			this.originalTitle = document.title;
		},
		render: function() {
			var entries_in_queue = liveblog.queue.length,
				count_string = entries_in_queue? '(' + entries_in_queue + ') ' : '';
			document.title = count_string + this.originalTitle;
		}
	});

	// A dummy proxy DOM element, which allows us to use arbitrary events
	// via the jQuery events system
	liveblog.$events = $( '<span />' );

	liveblog.init = function() {
		liveblog.$entry_container     = $( '#liveblog-entries'        );
		liveblog.$key_entry_container = $( '#liveblog-key-entries'    );
		liveblog.$spinner             = $( '#liveblog-update-spinner' );

		liveblog.queue = new liveblog.EntriesQueue();
		liveblog.fixedNag = new liveblog.FixedNagView();
		liveblog.entriesContainer = new liveblog.EntriesView();
		liveblog.titleBarCount = new liveblog.TitleBarCountView();
		liveblog.$events.trigger( 'after-views-init' );

		liveblog.init_moment_js();

		liveblog.cast_settings_numbers();
		liveblog.reset_timer();
		liveblog.set_initial_timestamps();
		liveblog.start_human_time_diff_timer();

		// Notifications if not admin
		if ( ! liveblog_settings.is_admin && 'Notification' in window ) {
			liveblog.set_up_notification_settings();
		}

		liveblog.$events.trigger( 'after-init' );
	};

	liveblog.init_moment_js = function() {
		momentLang.relativeTime = _.extend(moment().lang().relativeTime, momentLang.relativeTime);
		moment.lang(momentLang.locale, momentLang);
	};

	liveblog.set_initial_timestamps = function() {
		var now = liveblog.current_timestamp();
		liveblog.latest_entry_timestamp           = liveblog_settings.latest_entry_timestamp || 0;
		liveblog.latest_response_local_timestamp  = now;
		liveblog.latest_response_server_timestamp = now;
	};

	// wp_localize_scripts makes all integers into strings, and in JS
	// we need them to be real integers, so that we can use them in
	// arithmetic operations
	liveblog.cast_settings_numbers = function() {
		liveblog_settings.refresh_interval        = parseInt( liveblog_settings.refresh_interval, 10 );
		liveblog_settings.max_consecutive_retries = parseInt( liveblog_settings.max_consecutive_retries, 10 );
		liveblog_settings.delay_threshold         = parseInt( liveblog_settings.delay_threshold, 10 );
		liveblog_settings.delay_multiplier        = parseFloat( liveblog_settings.delay_multiplier, 10 );
		liveblog_settings.latest_entry_timestamp  = parseInt( liveblog_settings.latest_entry_timestamp, 10 );
		liveblog_settings.fade_out_duration       = parseInt( liveblog_settings.fade_out_duration, 10 );
	};

	liveblog.kill_timer = function() {
		clearTimeout( liveblog.refresh_timeout );
	};

	liveblog.reset_timer = function() {
		liveblog.kill_timer();
		liveblog.refresh_timeout = setTimeout( liveblog.get_recent_entries, ( liveblog_settings.refresh_interval * 1000 ) );
	};

	liveblog.undelay_timer = function() {
		if ( liveblog_settings.original_refresh_interval ) {
			liveblog_settings.refresh_interval = liveblog_settings.original_refresh_interval;
		}
	};

	liveblog.delay_timer = function() {
		if ( ! liveblog_settings.original_refresh_interval ) {
			liveblog_settings.original_refresh_interval = liveblog_settings.refresh_interval;
		}

		liveblog_settings.refresh_interval *= liveblog_settings.delay_multiplier;

	};

	liveblog.start_human_time_diff_timer = function() {
		var tick = function(){ liveblog.entriesContainer.updateTimes(); };
		tick();
		setInterval(tick, 60 * 1000);
	};

	liveblog.get_recent_entries = function() {
		var url  = liveblog_settings.endpoint_url,
			from = liveblog.latest_entry_timestamp + 1,
			local_diff = liveblog.current_timestamp() - liveblog.latest_response_local_timestamp,
			to         = liveblog.latest_response_server_timestamp + local_diff;

		url += from + '/' + to + '/';
		liveblog.show_spinner();
		liveblog.ajax_request( url, {}, liveblog.get_recent_entries_success, liveblog.get_recent_entries_error );
	};

	liveblog.get_recent_entries_success = function( response, status, xhr ) {
		var added, modifying;

		liveblog.consecutive_failures_count = 0;

		liveblog.hide_spinner();

		if ( response && response.latest_timestamp ) {
			liveblog.latest_entry_timestamp = response.latest_timestamp;
		}

		liveblog.latest_response_server_timestamp = liveblog.server_timestamp_from_xhr( xhr );
		liveblog.latest_response_local_timestamp  = liveblog.current_timestamp();

		if ( response.entries.length ) {
			if ( liveblog.is_at_the_top() && liveblog.queue.isEmpty() ) {
				liveblog.display_entries( response.entries );
			} else {
				added =  _.filter(response.entries, function(entry) { return 'new' === entry.type; } );
				modifying =  _.filter(response.entries, function(entry) { return 'update' === entry.type || 'delete' === entry.type; } );
				liveblog.queue.add(added);
				liveblog.queue.applyModifyingEntries(modifying);
				// updating and deleting entries is rare enough, so that we can screw the user's scroll and not queue those events
				liveblog.display_entries(modifying);
			}
		}

		liveblog.reset_timer();
		liveblog.undelay_timer();
	};

	liveblog.get_recent_entries_error = function() {

		liveblog.hide_spinner();

		// Have a max number of checks, which causes the auto-update to shut off or slow down the auto-update
		if ( ! liveblog.consecutive_failures_count ) {
			liveblog.consecutive_failures_count = 0;
		}

		liveblog.consecutive_failures_count++;

		if ( 0 === liveblog.consecutive_failures_count % liveblog_settings.delay_threshold ) {
			liveblog.delay_timer();
		}

		if ( liveblog.consecutive_failures_count >= liveblog_settings.max_consecutive_retries ) {
			liveblog.kill_timer();
			return;
		}

		liveblog.reset_timer();
	};

	liveblog.display_entries = function( entries ) {

		if ( !entries || ! entries.length ) {
			return;
		}

		// if we insert a few entries at once we should give the user more time to
		// seperate new from old ones
		var duration = entries.length * 1000 * liveblog_settings.fade_out_duration,
			i,
			entry;

		for ( i = 0; i < entries.length; i++ ) {
			entry = entries[i];
			liveblog.display_entry( entry, duration );
		}
	};

	liveblog.get_entry_by_id = function( id ) {
		return $( '#liveblog-entry-' + id );
	};

	liveblog.display_entry = function( new_entry, duration ) {
		if ( new_entry instanceof liveblog.Entry ) {
			new_entry = new_entry.attributes;
		}

		var $entry = liveblog.get_entry_by_id( new_entry.id );
		if ('new' === new_entry.type && !$entry.length) {
			liveblog.add_entry( new_entry, duration );
		} else if ('update' === new_entry.type && $entry.length) {
			liveblog.update_entry( $entry, new_entry );
		} else if ('delete' === new_entry.type && $entry.length) {
			liveblog.delete_entry( $entry );
		}

		$( document.body ).trigger( 'post-load' );
	};

	liveblog.add_entry = function( new_entry, duration ) {
		var $new_entry = $( new_entry.html );

		if ( $new_entry.hasClass('type-key') ) {
			var $new_key_entry = $( new_entry.html );
			$new_key_entry.addClass('highlight').prependTo( liveblog.$key_entry_container ).animate({backgroundColor: 'white'}, {duration: duration});
		}

		$new_entry.addClass('highlight').prependTo( liveblog.$entry_container ).animate({backgroundColor: 'white'}, {duration: duration});
		liveblog.entriesContainer.updateTimes();

		liveblog.notify($new_entry);
	};

	liveblog.update_entry = function( $entry, updated_entry ) {
		$entry.replaceWith( updated_entry.html );
		liveblog.entriesContainer.updateTimes();
	};

	liveblog.delete_entry = function( $entry ) {
		$entry.remove();
	};

	liveblog.get_all_entries = function() {
		return liveblog.$entry_container.find( '.liveblog-entry' );
	};

	liveblog.get_hidden_entries = function() {
		return liveblog.get_all_entries().filter( '.liveblog-hidden' );
	};

	liveblog.get_visible_entries = function() {
		return liveblog.get_all_entries().not( '.liveblog-hidden' );
	};

	liveblog.unhide_entries = function() {
		liveblog.get_hidden_entries().addClass('highlight').removeClass( 'liveblog-hidden' ).animate({backgroundColor: 'white'}, {duration: 5000});
	};

	liveblog.ajax_request = function( url, data, success_callback, error_callback, method ) {
		if ( 'function' !== typeof( success_callback ) ) {
			success_callback = liveblog.success_callback;
		}

		if ( 'function' !== typeof( error_callback ) ) {
			error_callback = liveblog.error_callback;
		}

		method = method || 'GET';

		$.ajax( {
			url: url,
			data: data,
			type: method,
			dataType: 'json',
			success: success_callback,
			error: error_callback
		} );
	};

	liveblog.success_callback = function() {};
	liveblog.error_callback   = function() {};

	liveblog.add_error = function( response, status ) {
		var message;
		if (response.status && response.status > 200) {
			message = liveblog_settings.error_message_template.replace('{error-code}', response.status).replace('{error-message}', response.statusText);
		} else {
			message = liveblog_settings.short_error_message_template.replace('{error-message}', status);
		}
		alert(message);
	};

	liveblog.show_spinner = function() {
		liveblog.$spinner.spin( 'small' );
	};

	liveblog.hide_spinner = function() {
		liveblog.$spinner.spin( false );
	};

	liveblog.current_timestamp = function() {
		return Math.floor( new Date().getTime() / 1000 );
	};

	liveblog.server_timestamp_from_xhr = function(xhr) {
		var timestamp_milliseconds = Date.parse( xhr.getResponseHeader( 'Date' ) );
		return Math.floor( timestamp_milliseconds / 1000 );
	};

	liveblog.is_at_the_top = function() {
		return $(document).scrollTop()  < liveblog.$entry_container.offset().top;
	};

	liveblog.set_up_notification_settings = function() {

		// Cache DOM elements
		liveblog.$notification_tags = $('.liveblog-notification-tags'),
		liveblog.$checkbox_enable = $('.liveblog-notification-enable'),
		liveblog.$checkbox_key = $('.liveblog-notification-key'),
		liveblog.$checkbox_alerts = $('.liveblog-notification-alerts'),
		liveblog.$notification_options = $('.liveblog-notification-options'),
		liveblog.$notification_settings = $('.liveblog-notification-settings'),
		liveblog.$notification_settings_toggle = $('.liveblog-notification-settings-toggle'),
		liveblog.$notification_settings_container = $('.liveblog-notification-settings-container');

		// Get currently stored tags
		liveblog.stored_tags = liveblog.parse_local_storage('liveblog-tags'),

		// Show settings container if browser suppoorts the Notification API
		liveblog.$notification_settings_container.show();

		// Hide settings
		liveblog.$notification_settings.hide();
		liveblog.$notification_options.hide();

		// Check notification status on load, use the `load` event
		liveblog.check_notification_status('load');

		// Populate tag input
		if ( liveblog.stored_tags ) {
			liveblog.$notification_tags.val(liveblog.stored_tags.join(' '));
		}

		// Populate key event checkbox
		if ( liveblog.parse_local_storage('liveblog-key') ) {
			liveblog.$checkbox_key.attr('checked', true);
		} else {
			liveblog.$checkbox_key.attr('checked', false);
		}

		// Populate alerts checkbox
		if ( liveblog.parse_local_storage('liveblog-alerts') ) {
			liveblog.$checkbox_alerts.attr('checked', true);
		} else {
			liveblog.$checkbox_alerts.attr('checked', false);
		}

		// Watch enable checkbox for any change
		liveblog.$checkbox_enable.on( 'change', function() {
			if ( this.checked ) {
				liveblog.check_notification_status('checked');
			} else {
				liveblog.check_notification_status('unchecked');
			}
		} );

		// Watch key checkbox for any change
		liveblog.$checkbox_key.on( 'change', function() {
			if ( this.checked ) {
				localStorage.setItem('liveblog-key', true);
			} else {
				localStorage.setItem('liveblog-key', false);
			}
		} );

		// Watch alerts checkbox for any change
		liveblog.$checkbox_alerts.on( 'change', function() {
			if ( this.checked ) {
				localStorage.setItem('liveblog-alerts', true);
			} else {
				localStorage.setItem('liveblog-alerts', false);
			}
		} );

		// Toggle notification settings
		liveblog.$notification_settings_toggle.on( 'click', function( e ) {
			e.preventDefault();
			liveblog.$notification_settings.slideToggle(250);
		} );

		// Auto save with debounced keyup
		liveblog.$notification_tags.on( 'keyup', _.debounce(liveblog.store_tags, 750) );
	};

	liveblog.set_notification_status = function( status ) {
		var checked, animation;

		if ( status === 'granted' ) {
			checked = true;
			animation = 'slideDown';
		} else if ( status === 'denied' ) {
			checked = false;
			animation = 'slideUp';
		}

		liveblog.$checkbox_enable.attr('checked', checked);
		liveblog.$notification_options[animation](250);
		localStorage.setItem('liveblog-notifications', checked);
	};

	liveblog.check_notification_status = function( event ) {
		if ( event === 'load' ) {

			// Handle if notifications permissions are not granted on load
			if ( Notification.permission !== 'granted' ) {
				liveblog.set_notification_status('denied');

			// If notifications are are granted and user enabled
			} else if ( Notification.permission === 'granted' && liveblog.parse_local_storage('liveblog-notifications') ) {
				liveblog.set_notification_status('granted');
			}

		} else if ( event === 'checked' ) {
			if ( Notification.permission === 'granted' ) {
				liveblog.set_notification_status('granted');

			} else if ( Notification.permission === 'denied' ) {
				liveblog.set_notification_status('denied');
				alert(liveblog_settings.notification_blocked_message);

			} else {
				Notification.requestPermission(function (permission) {
					if ( permission === 'granted' ) {
						liveblog.set_notification_status('granted');
					} else {
						liveblog.set_notification_status('denied');
					}
				});
			}

		} else if ( event === 'unchecked' ) {
			liveblog.set_notification_status('denied');
		}
	};

	liveblog.notify = function( $new_entry ) {

		/*
		1. Make sure admins don't recieve notifications
		2. Ensure user has enabled notifications (JSON parse for bool)
		3. Make sure the document doesn't have focus
		*/
		if ( liveblog_settings.is_admin || ! liveblog.parse_local_storage('liveblog-notifications') || document.hasFocus() ) {
			return;
		}

		var tags_length, notify, entry_text,
			entry_text = liveblog.get_notification_entry_text($new_entry),
			entry_icon = liveblog.get_notification_entry_icon($new_entry),
			type_key = liveblog.parse_local_storage('liveblog-key'),
			type_alerts = liveblog.parse_local_storage('liveblog-alerts');

		if ( type_alerts && $new_entry.hasClass(liveblog_settings.class_alert) ) {
			notify = true
		}

		if ( type_key && $new_entry.hasClass(liveblog_settings.class_key) ) {
			notify = true;
		}

		if ( liveblog.stored_tags ) {

			// Loop tags
			for ( var i = 0, tags_length = liveblog.stored_tags.length; i < tags_length; i++ ) {

				// Make sure tag is a saved one
				if ( $new_entry.hasClass(liveblog_settings.class_term_prefix + liveblog.stored_tags[i]) ) {
					notify = true;

					// Break out of loop as we limit to one notification at a time
					break;
				}
			}
		}

		if ( notify ) {
			liveblog.spawn_notification(liveblog_settings.notification_title, {body: entry_text, icon: entry_icon});
		}
	};

	liveblog.get_notification_entry_text = function( $entry ) {
		var original_content, entry_text;

		// Grab original content from data-attr
		original_content = $entry.find('.liveblog-entry-text').data('original-content');

		// Strip tags and emoji's (e.g. `:emoji_name:`)
		entry_text = original_content.replace(/(?::\w+: | ?:\w+:|<span[^<]*<\/span>|<\/?[^>]*>)/g, '');

		// Remove duplicate spaces
		entry_text = entry_text.replace(/(?:^\s+|\s*$|(\s)\s+)/g, '$1');

		return entry_text;
	};

	liveblog.get_notification_entry_icon = function( $entry ) {
		return liveblog_settings.notification_icon ||
				$entry.find('.liveblog-entry-text img:not(.liveblog-emoji):first').attr('src') ||
				$entry.find('.liveblog-author-avatar .avatar').attr('src') ||
				false;
	};

	liveblog.spawn_notification = function( title, opts ) {
		var options = opts || {},
			notification = new Notification(title, options);

		// Make sure it closes as Chrome currently (v43.0.2357.130) doesn't auto-close
		setTimeout(notification.close.bind(notification), 5000);
	},

	liveblog.store_tags = function( e ) {
		e.preventDefault();

		var tags_input_value = liveblog.$notification_tags.val(),
			tags = tags_input_value.split(' ');

		// Make sure data is new
		if ( JSON.stringify(liveblog.stored_tags) === JSON.stringify(tags) ) {
			return;
		}

		// Clean any empties / false values
		tags = _.compact(tags);

		// Filter out duplicates
		tags = _.uniq(tags);

		// Save to stored_tags
		liveblog.stored_tags = tags;

		// Store as array in localStorage
		localStorage.setItem('liveblog-tags', JSON.stringify(tags));

		$('.liveblog-notification-saved').fadeIn(300)
			.delay(750)
			.queue(function() {
				$(this).fadeOut(300);
				$(this).dequeue();
			});
	};

	// JSON parse localStorage key, useful for returning actual bool, array etc.
	liveblog.parse_local_storage = function( key ) {
		return JSON.parse(localStorage.getItem(key));
	};

	// Initialize everything!
	if ( 'archive' !== liveblog_settings.state ) {
		$( document ).ready( liveblog.init );
	}

} )( jQuery );
