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
			if ( status != 'error') return;
			show_error('Error: ' + xhr.status + ' ' + xhr.statusText);
  		});
	});
});
