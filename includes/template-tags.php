<?php
/**
 * Template tags for classic themes.
 *
 * Usage, inside the loop:
 *
 *     <article <?php beacon_tracker_attributes(); ?> <?php post_class(); ?>>
 *
 * @package Beacon_Tracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * The tracking attributes for an element, escaped.
 *
 * @param int|string|null $id   What's being tracked. Defaults to the current post ID.
 * @param int|string      $item Optional sub item.
 * @return string
 */
function beacon_tracker_get_attributes( $id = null, $item = '' ) {
	if ( null === $id ) {
		$id = get_the_ID();
	}
	if ( ! $id ) {
		return '';
	}

	$attributes = sprintf( 'data-track-id="%s"', esc_attr( $id ) );
	if ( '' !== (string) $item ) {
		$attributes .= sprintf( ' data-track-item="%s"', esc_attr( $item ) );
	}
	return $attributes;
}

/**
 * Print the tracking attributes for an element.
 *
 * @param int|string|null $id   What's being tracked. Defaults to the current post ID.
 * @param int|string      $item Optional sub item.
 */
function beacon_tracker_attributes( $id = null, $item = '' ) {
	echo beacon_tracker_get_attributes( $id, $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in beacon_tracker_get_attributes().
}
