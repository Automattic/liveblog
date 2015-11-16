/* global jQuery, liveblog, liveblog_settings, liveblogLazyloaderSettings */
( function ( $, settings ) {
	"use strict";

	var lazyloader = {

		initialize: function() {
			if ( ! liveblog.$entry_container.length ) {
				return;
			}

			lazyloader.fetchEntries();

			if ( liveblog.$key_entry_container.length ) {
				liveblog.$key_entry_container.on( 'click', 'a', lazyloader.clickKeyEventLink );
			}

			liveblog.$entry_container.on( 'click', '.liveblog-load-more', lazyloader.renderEntries );
		},

		fetchEntries: function() {
			lazyloader.entrySets = [];

			$.get( liveblog_settings.endpoint_url + 'lazyload', {}, function( response ) {
				if ( ! response.entries.length ) {
					$( '.liveblog-load-more' ).remove();
				} else {
					lazyloader.entrySets[0] = response.entries;
				}
			} );
		},

		clickKeyEventLink: function() {
			var $this = $( this );
			if ( $( $this.attr( 'href' ) ).length ) {
				return;
			}

			var entryID = $this.data( 'entry-id' );
			if ( ! entryID ) {
				return;
			}

			var setIndex = lazyloader.findEntrySet( entryID );
			if ( setIndex === -1 ) {
				return;
			}

			var $button = liveblog.$entry_container.find( '.liveblog-load-more[data-set-index="' + setIndex + '"]' );
			lazyloader.renderEntries.call( $button );
		},

		findEntrySet: function( entryID ) {
			var newSetIndex = -1;

			$.each( lazyloader.entrySets, function( setIndex, entries ) {
				$.each( entries, function( entryIndex, entry ) {
					if ( entry.id != entryID || newSetIndex !== -1 ) {
						return;
					}

					if ( entryIndex > 0 ) {
						newSetIndex = lazyloader.splitEntrySet( setIndex, entryIndex );
					} else {
						newSetIndex = setIndex;
					}
				} );
			} );

			return newSetIndex;
		},

		splitEntrySet: function( setIndex, entryIndex ) {
			var newSetIndex = lazyloader.entrySets.length;

			lazyloader.entrySets[ newSetIndex ] = lazyloader.entrySets[ setIndex ].slice( entryIndex );
			lazyloader.entrySets[ setIndex ] = lazyloader.entrySets[ setIndex ].slice( 0, entryIndex );

			liveblog.$entry_container.find( '.liveblog-load-more[data-set-index="' + setIndex + '"]' )
				.after( $( '<button class="liveblog-load-more">' ).attr( 'data-set-index', newSetIndex ).html( settings.loadMoreButtonText ) );

			return newSetIndex;
		},

		renderEntries: function() {
			var $this = $( this ),
				setIndex = $this.data( 'set-index' ) || 0;
			$.each( lazyloader.entrySets[ setIndex ].slice( 0, settings.numberOfEntries ), function( i, entry ) {
				$this.before( $( entry.html ) );
			} );

			lazyloader.entrySets[ setIndex ] = lazyloader.entrySets[ setIndex ].slice( settings.numberOfEntries );
			if ( lazyloader.entrySets[ setIndex ].length ) {
				$this.blur();
			} else {
				$this.remove();
			}
		}
	};

	$( lazyloader.initialize );

} )( jQuery, liveblogLazyloaderSettings );
