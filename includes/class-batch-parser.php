<?php
/**
 * Validates a batch sent by the tracker and turns it into daily counts.
 *
 * Plain PHP with no WordPress calls, so it can be unit tested on its own.
 * Same rules as server/collect.js in the beacon-tracker repo.
 *
 * @package Beacon_Tracker
 */

namespace Beacon_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Batch parser.
 */
class Batch_Parser {

	/**
	 * Crawlers that run JS trigger impressions too, and there are a lot of them.
	 */
	const BOT_UA = '#bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|facebookexternalhit|embedly|python|curl|wget|httpclient|java/#i';

	const ID_PATTERN   = '/^[A-Za-z0-9_-]{1,64}$/';
	const PAGE_PATTERN = '#^/[A-Za-z0-9_\-/:.]{0,99}$#';
	const MAX_ITEMS    = 500;

	/**
	 * Parse one request body.
	 *
	 * @param string        $body       Raw request body.
	 * @param string        $user_agent User-Agent header.
	 * @param string        $day        Day to count against, Y-m-d.
	 * @param string[]|null $types      Only accept these event types, or null for any.
	 * @return array<int, array{day: string, id: string, page: string, type: string, count: int}>
	 */
	public static function parse( $body, $user_agent, $day, $types = null ) {
		if ( '' === (string) $user_agent || preg_match( self::BOT_UA, $user_agent ) ) {
			return array();
		}

		$data = json_decode( (string) $body, true );
		if ( ! is_array( $data ) || ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return array();
		}

		// Don't trust the client to dedupe impressions. Clicks can repeat though.
		$impressions = array();
		$totals      = array();

		foreach ( array_slice( $data['items'], 0, self::MAX_ITEMS ) as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$id   = self::field( $raw, 'id' );
			$item = self::field( $raw, 'item' );
			$page = self::field( $raw, 'page' );
			$type = self::field( $raw, 'type' );

			if ( ! preg_match( self::ID_PATTERN, $id ) || ( '' !== $item && ! preg_match( self::ID_PATTERN, $item ) ) ) {
				continue;
			}
			if ( ! preg_match( self::PAGE_PATTERN, $page ) || ! preg_match( self::ID_PATTERN, $type ) ) {
				continue;
			}
			if ( null !== $types && ! in_array( $type, $types, true ) ) {
				continue;
			}

			if ( 'impression' === $type ) {
				$seen_key = "$id:$item:$page";
				if ( isset( $impressions[ $seen_key ] ) ) {
					continue;
				}
				$impressions[ $seen_key ] = true;
			}

			$key = "$id|$page|$type";
			if ( isset( $totals[ $key ] ) ) {
				++$totals[ $key ]['count'];
			} else {
				$totals[ $key ] = array(
					'day'   => $day,
					'id'    => $id,
					'page'  => $page,
					'type'  => $type,
					'count' => 1,
				);
			}
		}

		return array_values( $totals );
	}

	/**
	 * A field as a string, or '' if it's missing or not a scalar.
	 *
	 * @param array  $raw  Item from the batch.
	 * @param string $name Field name.
	 * @return string
	 */
	private static function field( array $raw, $name ) {
		if ( ! isset( $raw[ $name ] ) || ! is_scalar( $raw[ $name ] ) ) {
			return '';
		}
		return (string) $raw[ $name ];
	}
}
