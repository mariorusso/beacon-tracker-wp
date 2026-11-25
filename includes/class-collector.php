<?php
/**
 * REST endpoint the tracker sends its beacons to.
 *
 * @package Beacon_Tracker
 */

namespace Beacon_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Collector.
 */
class Collector {

	const REST_NAMESPACE = 'beacon-tracker/v1';
	const MAX_BODY       = 65536;

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * POST /wp-json/beacon-tracker/v1/collect
	 */
	public function register_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/collect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'collect' ),
				// Public on purpose, anonymous visitors send these.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Save a batch. Always answers 204, the browser never reads the response.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function collect( \WP_REST_Request $request ) {
		$body = $request->get_body();

		if ( strlen( $body ) <= self::MAX_BODY ) {
			/**
			 * Event types to accept, or null to accept any valid type.
			 *
			 * @param string[]|null $types
			 */
			$types = apply_filters( 'beacon_tracker_allowed_types', null );

			$rows = Batch_Parser::parse(
				$body,
				(string) $request->get_header( 'user_agent' ),
				gmdate( 'Y-m-d' ),
				$types
			);

			/**
			 * Filter the rows before they're saved, e.g. to only keep ids of
			 * published posts. Anyone can post to this endpoint.
			 *
			 * @param array $rows Rows with day, id, page, type and count.
			 */
			$rows = apply_filters( 'beacon_tracker_rows', $rows );

			if ( $rows ) {
				$this->save( $rows );
			}
		}

		$response = new \WP_REST_Response( null, 204 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Add the counts with one query.
	 *
	 * @param array $rows Rows from Batch_Parser::parse().
	 */
	private function save( array $rows ) {
		global $wpdb;

		$placeholders = array();
		$values       = array();
		foreach ( $rows as $row ) {
			$placeholders[] = '(%s, %s, %s, %s, %d)';
			array_push( $values, $row['day'], $row['id'], $row['page'], $row['type'], $row['count'] );
		}

		$table = Installer::table();
		$sql   = "INSERT INTO {$table} (day, track_id, page, event_type, hits) VALUES "
			. implode( ', ', $placeholders )
			. ' ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- placeholders are built above, it's a write.
		$wpdb->query( $wpdb->prepare( $sql, $values ) );
	}
}
