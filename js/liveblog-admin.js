jQuery(function($) {
	var $meta_box = $('#liveblog');
	var show_error = function(message) {
		$('p.error', $meta_box).show().html(message);
 	}
	$meta_box.on('click', 'button', function(e) {
		e.preventDefault();
		var url = ajaxurl + '?action=set_liveblog_state_for_post&post_id=1&state=' + $(this).val();
		$('.inside', $meta_box).load(url, function(response, status, xhr) {
			if ( status != 'error') return;
			show_error('Error: ' + xhr.status + ' ' + xhr.statusText);
  		});
	});
});
