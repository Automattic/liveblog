<?php

namespace WPCOM\Liveblog\Libraries;

class Permissions {

	public function open() {
		return true;
	}

	public function edit_others_posts()  {
		return current_user_can( 'edit_others_posts' );
	}
}