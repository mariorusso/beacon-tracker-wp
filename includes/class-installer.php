<?php
/**
 * Creates and upgrades the counts table.
 *
 * @package Beacon_Tracker
 */

namespace Beacon_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	const DB_VERSION = '1';
	const OPTION     = 'beacon_tracker_db_version';

	/**
	 * Table name with the site's prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'beacon_tracker_daily';
	}

	/**
	 * Create or update the table. dbDelta is picky about formatting (two
	 * spaces after PRIMARY KEY, one column per line).
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				day date NOT NULL,
				track_id varchar(64) NOT NULL,
				page varchar(100) NOT NULL,
				event_type varchar(64) NOT NULL,
				hits int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,track_id,page,event_type),
				KEY day (day)
			) {$charset};"
		);

		update_option( self::OPTION, self::DB_VERSION, false );
	}

	/**
	 * Run install() again when the schema version changes, since activation
	 * hooks don't run on plugin updates.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}
}
