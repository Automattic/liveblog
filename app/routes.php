<?php 

use WPCOM\Liveblog\Libraries\Router as Router;

/**
* Define routes for plugin
*/
$router = new Router( 'liveblog/v2' );

/**
* Public/Open routes
*/
$router->group( 'WPCOM\Liveblog\Controllers', 'WPCOM\Liveblog\Libraries\Permissions@open', function () use ( $router ) {

	$router->get( '/post/{post}/entries/{entry}', 		'Entry_Controller@all' );
	$router->get( '/post/{post}/entry/{entry}', 		'Entry_Controller@get' );
	$router->get( '/post/{post}/polling/{token}',   	'Entry_Controller@polling' );

});

/**
* Admin/Editor routes
*/
$router->group( 'WPCOM\Liveblog\Controllers', 'WPCOM\Liveblog\Libraries\Permissions@edit_others_posts', function () use ( $router ) {

	$router->post(   '/post/{post}/entry', 			'Entry_Controller@create' );
	$router->patch(  '/post/{post}/entry/{entry}',  'Entry_Controller@update' );
	$router->delete( '/post/{post}/entry/{entry}',  'Entry_Controller@delete' );

});

