<?php

/**
 * Class Liveblog_Helpers
 *
 * Hooks and constants
 */
class Liveblog_Helpers {
	public static $meta_box_allowed_tags = [
		'p'      => [],
		'select' => [
			'id'    => [],
			'class' => [],
			'name'  => [],
		],
		'option' => [
			'value'    => [],
			'selected' => [],
		],
		'label'  => [
			'for' => [],
		],
		'input'  => [
			'id'    => [],
			'class' => [],
			'type'  => [],
			'value' => [],
			'name'  => [],
		],
		'button' => [
			'id'    => [],
			'class' => [],
			'type'  => [],
			'value' => [],
		],
	];

}
