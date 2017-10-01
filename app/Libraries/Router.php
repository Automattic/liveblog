<?php

namespace WPCOM\Liveblog\Libraries;

class Router {

    /**
     * Store any created routes.
 	 *
     * @var array
     */
    protected $routes = [];

    /**
     * @var string
     */
    protected $parameter_pattern = '/{([\w\d]+)}/';

    /**
     * @var string
     */
    protected $value_pattern = '(?P<$1>[^\/]+)';

    /**
     * @var string
     */
    protected $rest_namespace = '';

    /**
     * @var string
     */
    protected $namespace = '';

    /**
     * @var string
     */
    protected $permissions = false;

    /**
     * Adds the action hooks for WordPress.
     */
    public function __construct( $rest_namespace = '' )
    {
        $this->rest_namespace = $rest_namespace;

        add_action('rest_api_init', array( $this, 'boot' ) );
    }

    /**
     * Boot the router.
     *
     * @return void
     */
    public function boot()
    {
        foreach ( $this->routes as $route ) 
        {
            $this->add_route( $route );
        }
    }

     /**
     *  Starts a new router group.
     *
     * @param  $attrs
     * @return void
     */
    public function group( $namespace, $premissions, $callback )
    {
        $this->namespace   = $namespace;
        $this->premissions = $premissions;

        $this->fetch( $callback, [] );

        $this->namespace   = '';
        $this->premissions = false;
    }

    /**
     * Add a route using register_rest_route
     *
     * @return void
     */
    public function add_route( $route ) 
    {
        $uri = preg_replace(
            $this->parameter_pattern,
            $this->value_pattern,
            $route['uri']
        );

        $route['callback'] = $this->create_valid_callback( $route['callback'] );

        $args = [
            'methods'  => $route['method'],
            'callback' => $route['callback'],
        ];

        if ( $route['permissions'] !== false ) {
            $args['permission_callback'] = $this->create_valid_callback( $route['permissions'] );
        }

        register_rest_route( $this->rest_namespace, $uri, $args );
    }

    /**
     * Takes Class@Method and returns [ new class, method ]
     *
     * @param $callback
     * @return array
     */
    public function create_valid_callback( $callback ) 
    {
        if ( is_string( $callback ) && strpos( $callback, '@' ) !== false )
        {
            list( $class, $method ) = explode( '@', $callback, 2 );
            $callback = [ new $class, $method ];
        }
        return $callback;
    }

    /**
     * Adds route to the Router.
     *
     * @param $method
     * @param $callback
     * @return bool
     */
    public function add( $method, $uri, $callback, $permissions = false )
    {
        if ( !empty( $this->namespace ) )
        {
            $callback = $this->namespace . '\\' . $callback;
        }

        if ( !empty( $this->premissions ) )
        {
            $permissions = $this->premissions;
        }

        $this->routes[] = [
            'method'      => $method,
            'uri'         => ltrim( $uri , '/' ),
            'callback'    => $callback,
            'permissions' => $permissions
        ];

        return true;
    }

    /**
     * Helper method for adding route.
     */
	public function get( $uri, $callback, $permissions = false )
	{
		return $this->add( 'GET', $uri, $callback, $permissions );
	}

    /**
     * Helper method for adding route.
     */
	public function post( $uri, $callback, $permissions = false )
	{
		return $this->add( 'POST', $uri, $callback, $permissions );
	}

    /**
     * Helper method for adding route.
     */
	public function put( $uri, $callback, $permissions = false )
	{
		return $this->add( 'PUT', $uri, $callback, $permissions );
	}

    /**
     * Helper method for adding route.
     */
	public function patch( $uri, $callback, $permissions = false)
	{
		return $this->add( 'PATCH', $uri, $callback, $permissions );
	}

    /**
     * Helper method for adding route.
     */
	public function delete( $uri, $callback, $permissions = false )
	{
		return $this->add( 'DELETE', $uri, $callback, $permissions );
	}

    /**
     * Fetches a controller or callbacks response.
     *
     * @param $callback
     * @param array $args
     * @return mixed
     */
    public function fetch( $callback, $args = array() )
    {
        if ( is_string( $callback ) )
        {
            list( $class, $method ) = explode( '@', $callback, 2 );
            $controller = new $class;
            return call_user_func_array( array( $controller, $method ), $args );
        }
        return call_user_func_array( $callback, $args );
    }
}