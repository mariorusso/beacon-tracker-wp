<?php
/**
 * Tools > Beacon Tracker: impressions and clicks per tracked id.
 *
 * @package Beacon_Tracker
 */

namespace Beacon_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Admin report page.
 */
class Admin_Report {

	const PAGE   = 'beacon-tracker';
	const RANGES = array( 7, 30, 90 );

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Add the page under Tools.
	 */
	public function add_page() {
		add_management_page(
			__( 'Beacon Tracker', 'beacon-tracker' ),
			__( 'Beacon Tracker', 'beacon-tracker' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Selected range in days, from ?days=.
	 *
	 * @return int
	 */
	private function days() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		return in_array( $days, self::RANGES, true ) ? $days : 30;
	}

	/**
	 * Totals per id for the range, impressions and clicks split out.
	 *
	 * @param int $days Number of days back, including today.
	 * @return array<string, array{impressions: int, clicks: int, types: array<string, int>}>
	 */
	private function totals( $days ) {
		global $wpdb;

		$table = Installer::table();
		$since = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is ours, the report should be live.
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT track_id, event_type, SUM(hits) AS hits FROM {$table} WHERE day >= %s GROUP BY track_id, event_type", $since ) );

		$totals = array();
		foreach ( (array) $results as $row ) {
			$id = $row->track_id;
			if ( ! isset( $totals[ $id ] ) ) {
				$totals[ $id ] = array(
					'impressions' => 0,
					'clicks'      => 0,
					'types'       => array(),
				);
			}
			if ( 'impression' === $row->event_type ) {
				$totals[ $id ]['impressions'] += (int) $row->hits;
			} else {
				$totals[ $id ]['clicks']                   += (int) $row->hits;
				$totals[ $id ]['types'][ $row->event_type ] = (int) $row->hits;
			}
		}

		uasort(
			$totals,
			function ( $a, $b ) {
				if ( $a['impressions'] !== $b['impressions'] ) {
					return $b['impressions'] <=> $a['impressions'];
				}
				return $b['clicks'] <=> $a['clicks'];
			}
		);
		return $totals;
	}

	/**
	 * Name for an id: the post title if it's a post ID, otherwise the id.
	 *
	 * @param string $id Tracked id.
	 * @return string HTML.
	 */
	private function label( $id ) {
		if ( ctype_digit( $id ) ) {
			$post = get_post( (int) $id );
			if ( $post ) {
				$link = get_edit_post_link( $post );
				$name = get_the_title( $post ) ? get_the_title( $post ) : $id;
				return $link
					? sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $name ) )
					: esc_html( $name );
			}
		}
		return '<code>' . esc_html( $id ) . '</code>';
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$days   = $this->days();
		$totals = $this->totals( $days );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Beacon Tracker', 'beacon-tracker' ); ?></h1>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label for="beacon-tracker-days"><?php esc_html_e( 'Show', 'beacon-tracker' ); ?></label>
				<select name="days" id="beacon-tracker-days">
					<?php foreach ( self::RANGES as $range ) : ?>
						<option value="<?php echo esc_attr( $range ); ?>" <?php selected( $days, $range ); ?>>
							<?php
							/* translators: %d: number of days */
							echo esc_html( sprintf( _n( 'Last %d day', 'Last %d days', $range, 'beacon-tracker' ), $range ) );
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Apply', 'beacon-tracker' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( ! $totals ) : ?>
				<p><?php esc_html_e( 'Nothing recorded in this period yet.', 'beacon-tracker' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="margin-top: 1em;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Tracked item', 'beacon-tracker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Impressions', 'beacon-tracker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Clicks', 'beacon-tracker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Click rate', 'beacon-tracker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Clicks by type', 'beacon-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $totals as $id => $row ) : ?>
							<tr>
								<td><?php echo $this->label( (string) $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in label(). ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
								<td class="num">
									<?php
									echo $row['impressions']
										? esc_html( number_format_i18n( 100 * $row['clicks'] / $row['impressions'], 1 ) . '%' )
										: '&ndash;';
									?>
								</td>
								<td>
									<?php
									arsort( $row['types'] );
									$parts = array();
									foreach ( $row['types'] as $type => $count ) {
										$parts[] = esc_html( $type . ' ' . number_format_i18n( $count ) );
									}
									echo implode( ', ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
