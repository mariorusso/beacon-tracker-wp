<?php
/**
 * Loads the tracker on the front end.
 *
 * @package Beacon_Tracker
 */

namespace Beacon_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Assets.
 */
class Assets {

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the script, deferred, and start it with the REST endpoint.
	 */
	public function enqueue() {
		/**
		 * Whether to load the tracker on this page.
		 *
		 * @param bool $enabled
		 */
		if ( ! apply_filters( 'beacon_tracker_enabled', true ) ) {
			return;
		}

		wp_enqueue_script(
			'beacon-tracker',
			plugins_url( 'assets/beacon-tracker.min.js', PLUGIN_FILE ),
			array(),
			VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		/**
		 * Options passed to BeaconTracker.init(). Only JSON values, so no
		 * `page`/`ignore` callbacks from here.
		 *
		 * @param array $config
		 */
		$config = apply_filters(
			'beacon_tracker_config',
			array(
				'endpoint' => rest_url( Collector::REST_NAMESPACE . '/collect' ),
			)
		);

		wp_add_inline_script( 'beacon-tracker', 'window.BeaconTracker.init(' . wp_json_encode( $config ) . ');' );
	}
}
