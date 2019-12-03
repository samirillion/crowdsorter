<?php
/**
 * This is where custom routes go! WordPress, of course, has a lot of routes (to posts, pages, etc.) configured out of the box.
 * For simple stuff "routing," just dropping shortcodes into pages works. But this can be quite unpredictable.
 * This Router is a wrapper around the WP Rest API.
 * But this is for defining all the HTTP stuff without using clunky hooks like admin_post etc.
 * Just a lot more scalable for a larger application.
 */
require_once( IFM_INC . 'router/class-route.php' );

// build up routes, composed of path and a callback
$classsss = IfmRoute::get( '/create-post', 'IfmPostsController@createview' )::auth( 'member' );
IfmRoute::get( '/fing-post', 'IfmPostsController@createview' );

// pass the routes object to the router
IfmRoute::register();
// add_action( 'rest_api_init', 'prefix_register_my_rest_routes' );
