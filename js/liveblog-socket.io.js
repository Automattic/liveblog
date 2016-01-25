var socket = new io('localhost:3000');

socket.on( 'liveblog entry ' + liveblog_settings.post_id, function( entry ) {
	var entry_object = jQuery.parseJSON( entry );

	if ( ! liveblog_settings.is_liveblog_editable ) {
		// If user doesn't have permission to edit or delete entries remove the action buttons
		var entry_html = jQuery( entry_object.html );
		entry_html.find( '.liveblog-entry-actions' ).remove();
		entry_object.html = entry_html.prop( 'outerHTML' );
	}

	liveblog.display_entry( entry_object, 5000 );
});
