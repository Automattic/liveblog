window.liveblog = {};

( function( $ ) {
	liveblog.EntriesView = Backbone.View.extend({
		el: '#liveblog-container',
		initialize: function() {
			var view = this;
			liveblog.queue.on('reset', this.scrollToTop, this);
			$(window).scroll($.throttle(250, this.flushQueueWhenOnTop));
		},
		scrollToTop: function() {
			$(window).scrollTop(this.$el.offset().top);
		},
		flushQueueWhenOnTop: function(e) {
			if (liveblog.is_at_the_top()) {
				liveblog.queue.flush();
			}
		},
		updateTimes: function() {
			this.$('.liveblog-entry').each(function() {
				var $entry = $(this),
					timestamp = $entry.data('timestamp'),
					human = moment.unix(timestamp).fromNow();
				$('.liveblog-meta-time a', $entry).text(human);
			});
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
		applyModifyingEntry: function(entry) {
			var existing = this.get(entry.id);
			if (!existing) {
				return;
			}
			if ("delete" == entry.type) {
				this.remove(existing);
			}
			if ("update" == entry.type) {
				existing.set("html", entry.html);
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
			var template = number==1? liveblog_settings.new_update : liveblog_settings.new_updates,
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
				count_string;
			if ( entries_in_queue ) {
				count_string = '(' + entries_in_queue + ')';
				document.title = document.title.replace( /^\(\d+\)\s+/, '' );
				document.title = count_string + ' ' + document.title;
			} else {
				document.title = this.originalTitle;
			}
		}
	});

	// A dummy proxy DOM element, which allows us to use arbitrary events
	// via the jQuery events system
	liveblog.$events = $( '<span />' );

	liveblog.init = function() {
		liveblog.$entry_container = $( '#liveblog-entries'        );
		liveblog.$spinner         = $( '#liveblog-update-spinner' );

		liveblog.queue = new liveblog.EntriesQueue();
		liveblog.fixedNag = new liveblog.FixedNagView();
		liveblog.entriesContainer = new liveblog.EntriesView();
		liveblog.titleBarCount = new liveblog.TitleBarCountView();

		liveblog.init_moment_js();

		liveblog.cast_settings_numbers();
		liveblog.reset_timer();
		liveblog.set_initial_timestamps();
		liveblog.start_human_time_diff_timer();

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
		var url  = liveblog_settings.endpoint_url;
		var from = liveblog.latest_entry_timestamp + 1;

		var local_diff = liveblog.current_timestamp() - liveblog.latest_response_local_timestamp;
		var to         = liveblog.latest_response_server_timestamp + local_diff;

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
				added =  _.filter(response.entries, function(entry) { return 'new' == entry.type; } );
				modifying =  _.filter(response.entries, function(entry) { return 'update' == entry.type || 'delete' == entry.type; } );
				liveblog.queue.add(added);
				liveblog.queue.applyModifyingEntries(modifying);
				// updating and deleting entries is rare enough, so that we can screw the user's scroll and not queue those events
				liveblog.display_entries(modifying);
			}
		}

		liveblog.reset_timer();
		liveblog.undelay_timer();
	};

	liveblog.get_recent_entries_error = function( response ) {

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
		var duration = entries.length * 1000 * liveblog_settings.fade_out_duration;

		for ( var i = 0; i < entries.length; i++ ) {
			var entry = entries[i];
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
		if ('new' == new_entry.type && !$entry.length)
			liveblog.add_entry( new_entry, duration );
		else if ('update' == new_entry.type && $entry.length)
			liveblog.update_entry( $entry, new_entry );
		else if ('delete' == new_entry.type && $entry.length)
			liveblog.delete_entry( $entry );

		$( document.body ).trigger( 'post-load' );
	};

	liveblog.add_entry = function( new_entry, duration ) {
		var $new_entry = $( new_entry.html );
		$new_entry.addClass('highlight').prependTo( liveblog.$entry_container ).animate({backgroundColor: 'white'}, {duration: duration});
		liveblog.entriesContainer.updateTimes();
	};

	liveblog.update_entry = function( $entry, updated_entry ) {
		var $updated_entry = $( updated_entry.html );
		var updated_text   = $( '.liveblog-entry-text', $updated_entry ).html();

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
		if ( 'function' !== typeof( success_callback ) )
			success_callback = liveblog.success_callback;

		if ( 'function' !== typeof( error_callback ) )
			error_callback = liveblog.error_callback;

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
		if (response.status && response.status > 200)
			message = liveblog_settings.error_message_template.replace('{error-code}', response.status).replace('{error-message}', response.statusText);
		else
			message = liveblog_settings.short_error_message_template.replace('{error-message}', status);
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

	// Initialize everything!
	if ( 'archive' != liveblog_settings.state ) {
		$( document ).ready( liveblog.init );
	}

} )( jQuery );
