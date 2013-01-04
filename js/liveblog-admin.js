jQuery(function($) {
	var $meta_box = $('#liveblog');
	var post_id = $('#post_ID').val();
	var show_error = function(message) {
		$('p.error', $meta_box).show().html(message);
 	}
	$meta_box.on('click', 'button', function(e) {
		e.preventDefault();
		var url = ajaxurl + '?action=set_liveblog_state_for_post&post_id=' + encodeURIComponent(post_id) + '&state=' + encodeURIComponent($(this).val()) + '&' + liveblog_admin_settings.nonce_key + '=' + liveblog_admin_settings.nonce;
		$('.inside', $meta_box).load(url, function(response, status, xhr) {
			var message;
			if ( status == 'success') return;
			if (xhr.status && xhr.status > 200)
				message =  liveblog_admin_settings.error_message_template.replace('{error-code}', xhr.status).replace('{error-message}', xhr.statusText);
			else
				message = liveblog_admin_settings.short_error_message_template.replace('{error-message}', status);
			show_error(message);
  		});
	});
});
