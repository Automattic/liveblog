var socket = new io('localhost:3000');

socket.on('new liveblog entry ' + liveblog_settings.post_id, function(entry) {
	liveblog.display_entry( jQuery.parseJSON( entry ), 5000 );
});
