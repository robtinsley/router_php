<?php

class bitari_Router #
{
	protected $routes = array();

	protected $standard_patterns = array(
		'int'   => '(0|-?[1-9][0-9]*)',
		'uint'  => '(0|[1-9][0-9]*)',
		'id'    => '([1-9][0-9]*)',
		'hex'   => '([0-9a-f]+)',
		'hex16' => '([0-9a-f]{16})',
		'hex20' => '([0-9a-f]{20})',
		'uuid'  => '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',
		'ip4'   => '(([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])',
		'dns'   => '(([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}))', // won't match a TLD on its own, assumes TLD is only a-z
		'email' => '(?i:[a-z0-9._%+-]+_([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}))', // some false negatives
	);

	const _STATE_HEADER = 'bitari_Router#v1';

	function __contruct( $arg = NULL, $options = array() )
	{
		if ( $arg === NULL ) {
			/* do nothing */
		} elseif ( is_string( $arg ) && substr( $arg, 0, strlen( self::_STATE_HEADER ) + 1 ) === self::_STATE_HEADER . '~' ) {
			if ( $this->set_state_string( $arg ) !== true ) {
				throw new Exception( 'Bad route-string in ' . __CLASS__ . ' constructor' );
			}
		} elseif ( is_string( $arg ) ) {
			if ( $this->load_state_file( $arg ) !== true ) {
				throw new Exception( 'Bad route-file in ' . __CLASS__ . ' constructor' );
			}
		} else {
			throw new Exception( 'Bad argument in ' . __CLASS__ . ' constructor' );
		}
	}

	public function get_state_string()
	{
		return self::_STATE_HEADER . '~' . serialize( $this->routes );
	}

	protected function set_state_string( $arg = NULL )
	{
		if ( !is_string( $arg ) ) {
			return false;
		}

		$sep1 = strpos( $arg, '~' );
		if ( !is_integer( $sep1 ) ) {
			return false;
		}

		if ( substr( $arg, 0, $sep1 ) !== self::_STATE_HEADER ) {
			return false;
		}

		$new_routes = unserialize( substr( $arg, $sep1 + 1 ) );
		if ( !is_array( $new_routes ) ) {
			return false;
		}

		$this->routes = $new_routes;

		return true;
	}

	public function save_state_file( $filename )
	{
		return file_put_contents( $filename, $this->get_state_string() );
	}

	protected function load_state_file( $filename )
	{
		return $this->set_state_string( file_get_contents( $filename ) );
	}

	public function _dump()
	{
#		var_dump( $this->routes );
		print_r( $this->routes );
	}

	protected function parse( $pattern, &$regex, &$names )
	{
		$regex = '';
		$names = array();

		$i = 0;
		$splits = preg_split( '/(<.*?>)/', $pattern, NULL, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY );
		foreach ( $splits as $split ) {
			if ( substr( $split, 0, 1 ) !== '<' ) {
				$regex .= preg_quote( $split );
				continue;
			}
			$nmatches = preg_match( '/^<([A-Za-z][A-Za-z_0-9]*|)(?:([~:=])(.*?)|()())>$/', $split, $matches );
			if ( $nmatches !== 1 ) {
				return false;
			}
			$i++;
			if ( $matches[1] !== '' ) {
				$names[$i] = $matches[1];
			}
			switch ( $matches[2] ) {
				case '':
					$regex .= '([^/?]*?)';
					break;
				case '=':
					$regex .= '(' . preg_quote( $matches[3] ) . ')';
					break;
				case ':':
					if ( !array_key_exists( $matches[3], $this->standard_patterns ) ) {
						return false;
					}
					$matches[3] = $this->standard_patterns[$matches[3]];
					$regex .= '(' . preg_replace( '/\((?=[^?]|)/', '(?:', $matches[3] ) . ')';
					break;
				case '~':
					$regex .= '(' . preg_replace( '/\((?=[^?]|)/', '(?:', $matches[3] ) . ')';
					break;
				default:
					return false;
			}
		}
		$regex = '<^' . $regex . '$>';
		return true;
	}

/*
	public function resource_handler( $args )
	{
		if ( !is_array( $args )
		  || !array_key_exists( '_resource', $args )
		  || !is_string( $args['_resource'] )
		  || !array_key_exists( '_action', $args )
		  || !is_string( $args['_action'] ) ) {
		  	return false;
		}
		$classname = $args['_resource'] . 'Resource';
		if ( !class_exists( $classname ) ) {
			return false;
		}
		$class = new $classname;
		$methodname = $args['_action'] . '_action';
		if ( !is_callable( array( $class, $methodname ) ) ) {
			return false;
		}
		unset( $args['_resource'], $args['_action'] );
		return $class->$methodname( $args );
	}

	public function connect_resource( $base, $name, $handler )
	{
		$this->connect( "GET {$base}{$name}",           $handler, array( '_resource' => $name, '_action' => 'index' ) );
		$this->connect( "GET {$base}{$name}/new",       $handler, array( '_resource' => $name, '_action' => 'new' ) );
		$this->connect( "POST {$base}{$name}/new",      $handler, array( '_resource' => $name, '_action' => 'create' ) );
		$this->connect( "GET {$base}{$name}/<id>",      $handler, array( '_resource' => $name, '_action' => 'show' ) );
		$this->connect( "GET {$base}{$name}/<id>/edit", $handler, array( '_resource' => $name, '_action' => 'edit' ) );
		$this->connect( "PATCH {$base}{$name}/<id>",    $handler, array( '_resource' => $name, '_action' => 'update' ) );
		$this->connect( "DELETE {$base}{$name}/<id>",   $handler, array( '_resource' => $name, '_action' => 'destroy' ) );
	}
*/

	public function connect( $request_pattern, $handler, $extra = NULL )
	{
		if ( preg_match( '#^(?:([A-Za-z]+)[ \t]+)?(/[^ \t\r\n]*)#', $request_pattern, $matches ) === 1 ) {
			if ( $matches[1] === '' ) {
				$method = 'ANY';
			} else {
				$method = strtoupper( $matches[1] );
			}
			$pattern = $matches[2];
		} else {
			return false;
		}
		unset( $matches );

		$r = $this->parse( $pattern, $regex, $names );
		if ( $r !== true ) {
			return $r;
		}

		array_push( $this->routes, array( $handler, $method, $regex, $names, $extra ) );

		return true;
	}

	public function lookup( $request, &$args, &$canonical = NULL )
	{
		if ( preg_match( '#^(?:([A-Za-z]+)[ \t]+)?(/[^ \t\r\n]*)#', $request_pattern, $matches ) === 1 ) {
			if ( $matches[1] === '' ) {
				$method = 'ANY';
			} else {
				$method = strtoupper( $matches[1] );
			}
			$uri = $matches[2];
		} else {
			return false;
		}
		unset( $matches );

		$numroutes = count( $this->routes );

		while ( true ) {
			for ( $i = 0; $i < $numroutes; $i++ ) {
				if ( $method !== 'ANY' && $method !== $this->routes[$i][0] && $this->routes[$i][0] !== 'ANY' ) {
					continue;
				}
				if ( preg_match( $this->routes[$i][2], $uri, $matches ) !== 1 ) {
					continue;
				}
				$args = array();
				foreach ( $this->routes[$i][3] as $j => $name ) {
					$args[$name] = $matches[$j];
				}
				if ( is_array( $this->routes[$i][4] ) ) {
					$args = array_merge( $args, $this->routes[$i][4] );
				}
				return $this->routes[$i][0];
			}
			if ( $method !== 'HEAD' ) {
				break;
			}
			$method = 'GET';
		}

		if ( func_num_args() < 3 ) {
			return false;
		}

		$uri2 = preg_replace( '<//+>', '/', "/$uri/" );
		if ( $uri2 !== $uri ) {
			$handler = $this->route( "$method $uri2", $args );
			if ( $handler !== false ) {
				$canonical = $uri2;
				return $handler;
			}
		}

		$uri3 = substr( $uri2, 0, -1 );
		if ( $uri3 !== '' ) {
			$handler = $this->route( "$method $uri3", $args );
			if ( $handler !== false ) {
				$canonical = $uri3;
				return $handler;
			}
		}

		return false;
	}
}
