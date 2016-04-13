<?php
/**
 * Conduit Integrations Library for WordPress
 *
 * @package Conduit
 * @version 0.3
 * @filesource https://github.com/getsunrise/imperative
 * @author Micah Wood <micah@newclarity.net>
 * @author Mike Schinkel <mike@newclarity.net>
 * @license http://opensource.org/licenses/gpl-2.0.php
 * @copyright Copyright (c) 2012, NewClarity LLC
 *
 */

if( ! function_exists( 'maybe_add_itemtype' ) ) {
	function maybe_add_itemtype( $value, $itemtype, $force = false ) {
		return Conduit::maybe_add_itemtype( $value, $itemtype, $force );
	}
}
if( ! function_exists( 'the_field' ) ) {
	function the_field( $field_name, $args = array() ) {
		Conduit::the_field( $field_name, $args );
	}
}
if( ! function_exists( 'get_field' ) ) {
	function get_field( $field_name, $args = array() ) {
		return Conduit::get_field( $field_name, $args );
	}
}
if( ! function_exists( 'add_handler' ) ) {
	function add_handler( $handler, $callable, $criteria = array() ) {
		Conduit::add_handler( $handler, $callable, $criteria );
	}
}
if( ! function_exists( 'locate_handler' ) ) {
	function locate_handler( $handlers, $parameters = array() ) {
		return Conduit::locate_handler( $handlers, $parameters );
	}
}
if( ! class_exists( 'Conduit' ) ) {
	class Conduit {
		static $handlers = array();
		static $itemtypes = array();

		static function maybe_add_itemtype( $value, $itemtype, $args ) {
			if ( ! isset( self::$itemtypes[$itemtype] ) && $args['schema'] )
				$value = '<span itemscope itemtype="http://schema.org/' . $itemtype . '">' . $value . '</span>';
			return $value;
		}

		static function the_field( $field_name, $args = array() ) {
			echo get_field( $field_name, $args );
		}

		private static function _get_top_level_args( &$args = array() ) {
			$top_level_args = array();
			foreach( array( 'before', 'after', 'itemtype' ) as $arg ) {
				if ( ! isset( $args[$arg] ) ) {
					$top_level_args[$arg] = false;
				} else {
					$top_level_args[$arg] = $args[$arg];
					unset( $args[$arg] );
				}
			}
			return $top_level_args;
		}
		static function get_field( $field_name, $args = array() ) {
			global $post;
			$save_post = $post;
			$args = wp_parse_args( $args, array(
				'schema' => true,		// Schema.org markup is applied if applicable
			));

			/**
			 * Grab variables used only at the top level that are not passed down.
			 * Unset them in $args if they are set.
			 * Top level variables are: 'before', 'after', 'itemtype'
			 */
			$local = self::_get_top_level_args( $args );

			/**
			 * Check to see if a container field previously set the same Schema.org itemtype (i.e. 'Person')
			 * If yes, let's not wrap this value with a Schema.org itemscope and itemtype, set to false.
			 */
			if ( $local['itemtype'] && isset( self::$itemtypes[$local['itemtype']] ) )
				$local['itemtype'] = false;

			/**
			 * If a Schema.org itemtype is (still) set, add to the stack of contained fields
			 * so itemtype won't be added again.
			 */
			if ( $local['itemtype'] ) {
				self::$itemtypes[$local['itemtype']] = true;
			}

			if ( isset( $args['post'] ) ) {
				$post = $args['post'];
			} else if ( isset( $args['post_id'] ) ) {
				$post = get_post( $args['post_id'] );
			}
			$args['post'] = $post;
			$post_id = $args['post_id'] = ! empty( $post->ID ) ? $post->ID : false;

			/**
			 * Look for the most specific "handler" for this field, if there is one.
			 */
			$handlers = array();
			if( isset( $args['post'] ) && is_object( $args['post'] ) && property_exists( $args['post'], 'post_type' ) ) {
				$handlers['field_post_type_name'] = "field_name={$field_name}&post_type={$args['post']->post_type}";
				$handlers['field_post_type'] = "post_type={$args['post']->post_type}";
			}
			$handlers['field_name'] = "field_name={$field_name}";
			$handlers['field'] = false;

			$value = locate_handler( $handlers, array( $field_name, $args ) );

			/**
			 * @deprecated The 'pre_get_field' hook will be removed soon.
			 * TODO (mikes 2012-09-10): Remove this hook once all dependent code is using handlers
			 */
			if ( is_null( $value ) ) {
				$value = apply_filters( 'pre_get_field', null, $field_name, $args );
			}

			if ( is_null( $value ) && $post_id ) {
				$value = get_post_meta( $post_id, "_{$field_name}", true );
				if( is_null( $value ) ) {
					$value = get_post_meta( $post_id, $field_name, true );
				}
			}
			if ( empty( $value ) ) {
				$value = apply_filters( 'empty_field', $value, $field_name, $args );
			} else {
				$value = apply_filters( 'get_field', $value, $field_name, $args );
			}
			$post = $save_post;

			if ( $value && ( $local['before'] || $local['after'] ) ) {
				$value = $local['before'] . $value . $local['after'];
			}
			/**
			 * If $local['itemtype'] has been passed in, assume we want to use it.
			 */
			if ( $value && $local['itemtype']  ) {
				$value = '<span itemscope itemtype="http://schema.org/' . $itemtype . '">' . $local['itemtype'] . '</span>';
			}

			/**
			 * Remove the Schema.org itemtype from the stack when the itemtype was specified by a caller and was not cleared.
			 * (See above comments where we add to this stack to understand this better.)
			 */
			if ( $local['itemtype'] )
				array_pop( self::$itemtypes );

			return $value;
		}
		/**
		 * Adds a handler.
		 *
		 * Adds a handler which is like a cross between a filter and a theme template file;
		 * called like a filter but only one is called.
		 *
		 * @static
		 * @param string $handler A string naming a handler, similar to an action or filter.
		 * @param string|array $callable A function name or an array with class/instance and method name
		 * @param bool|array $criteria Array of criteria that get converted into a URL-encoded string for handler selection
		 */
		static function add_handler( $handler, $callable, $criteria = false ) {
			if ( empty( $criteria ) )
				$criteria = 'any';
			if ( is_array( $criteria ) ) {
				ksort( $criteria ); // Ensure same order for key matching
				$criteria = http_build_query( $criteria );
			}
			self::$handlers[$handler][$criteria] = $callable;
		}
		/**
		 * Locate the best handler for an item, if a hander is available.
		 *
		 * @static
		 * @param array $handlers List of handler names (array keys) and handler criteria (array values) if order for matching.
		 * @param bool|array $parameters Array of parameters to pass to handler.
		 * @return mixed|null
		 */
		static function locate_handler( $handlers, $parameters = array() ) {
			/**
			 * @var null|array|string $callable
			 */
			$callable = null;
			foreach( $handlers as $handler => $criteria ) {
				/**
				 * Test this handler's criteria exist
				 */
				if ( empty( $criteria ) )
					$criteria = 'any';
				/**
				 * $criteria can be an array or a key-sorted URL-encoded string.
				 * If an array convert to string.
				 */
				else if ( is_array( $criteria ) ) {
					ksort( $criteria ); // Ensure same order for key matching
					$criteria = http_build_query( $criteria );
				}
				if ( isset( self::$handlers[$handler][$criteria] ) ) {
					/**
					 * If exist, this is the best handler
					 */
					$callable = self::$handlers[$handler][$criteria];
					break;
				}
			}
			$value = $callable ? call_user_func_array( $callable, $parameters ) : null;
			return $value;
		}
	}
}
