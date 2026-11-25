<?php
/**
 * Plugin Name:       Beacon Tracker
 * Description:       Counts how often posts and other tracked elements are seen and clicked on the front end. Stores daily totals only, no personal data.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Mario Russo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       beacon-tracker
 *
 * @package Beacon_Tracker
 */

namespace Beacon_Tracker;

defined( 'ABSPATH' ) || exit;

const VERSION     = '1.0.0';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/class-batch-parser.php';
require_once __DIR__ . '/includes/class-installer.php';
require_once __DIR__ . '/includes/class-collector.php';
require_once __DIR__ . '/includes/class-assets.php';
require_once __DIR__ . '/includes/class-post-template.php';
require_once __DIR__ . '/includes/class-admin-report.php';
require_once __DIR__ . '/includes/template-tags.php';

register_activation_hook( __FILE__, array( Installer::class, 'install' ) );

add_action(
	'plugins_loaded',
	function () {
		Installer::maybe_upgrade();

		( new Collector() )->register();
		( new Assets() )->register();
		( new Post_Template() )->register();

		if ( is_admin() ) {
			( new Admin_Report() )->register();
		}
	}
);
