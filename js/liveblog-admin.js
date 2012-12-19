jQuery(function($) {
	var $meta_box = $('#liveblog');
	$meta_box.on('click', 'button', function(e) {
		e.preventDefault();
		var url = ajaxurl + '?action=set_liveblog_state_for_post&post_id=1&state=' + $(this).val();
		$('.inside', $meta_box).load(url);
	});
});
