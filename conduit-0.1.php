<?php
/**
 * Conduit Integrations Library for WordPress
 *
 * @package Conduit
 * @version 0.1
 * @author Micah Wood <micah@newclarity.net>
 * @author Mike Schinkel <mike@newclarity.net>
 * @license http://opensource.org/licenses/gpl-2.0.php
 * @copyright Copyright (c) 2012, NewClarity LLC
 *
 */

function the_field( $field_name, $args = array() ) {
	echo get_field( $field_name, $args );
}

function get_field( $field_name, $args = array() ) {
	global $post;
	$save_post = $post;
	if ( is_string( $args ) ){
		parse_str( $args, $args );
	}
	if ( isset( $args['post'] ) ) {
		$post = $args['post'];
	} else if ( isset( $args['post_id'] ) ) {
		$post = get_post( $args['post_id'] );
	}
	$args['post'] = $post;
	$args['post_id'] = empty( $post->ID ) ? false : $post->ID;
	/**
	 * TODO: (Micah 2012-03-19) How can we short-circut this filter?
	 */
	$value = apply_filters( 'pre_get_field', null, $field_name, $args );
	if ( is_null( $value ) ) {
		$value = get_post_meta( $post->ID, "_{$field_name}", true );
		if( is_null( $value ) ) {
			$value = get_post_meta( $post->ID, $field_name, true );
		}
	}
	if ( $value ) {
		$value = apply_filters( 'get_field', $value, $field_name, $args );
	} else {
		$value = apply_filters( 'empty_field', $value, $field_name, $args );
	}
	$post = $save_post;
	$before = isset( $args['before'] ) ? $args['before']: '';
	$after = isset( $args['after'] ) ? $args['after']: '';
	if ( $value ) {
		return $before . $value . $after;
	}
	return $value;
}