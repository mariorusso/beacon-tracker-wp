<?php

use Beacon_Tracker\Batch_Parser;
use PHPUnit\Framework\TestCase;

class BatchParserTest extends TestCase {

	const UA  = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130';
	const DAY = '2026-01-15';

	private function body( array $items ) {
		return json_encode( array( 'items' => $items ) );
	}

	public function test_counts_clicks_every_time_and_impressions_once() {
		$rows = Batch_Parser::parse(
			$this->body(
				array(
					array( 'id' => 'a', 'item' => 'x', 'page' => '/', 'type' => 'impression' ),
					array( 'id' => 'a', 'item' => 'x', 'page' => '/', 'type' => 'impression' ),
					array( 'id' => 'a', 'item' => 'y', 'page' => '/', 'type' => 'impression' ),
					array( 'id' => 'a', 'item' => '', 'page' => '/', 'type' => 'click_phone' ),
					array( 'id' => 'a', 'item' => '', 'page' => '/', 'type' => 'click_phone' ),
				)
			),
			self::UA,
			self::DAY
		);

		$this->assertSame(
			array(
				array( 'day' => self::DAY, 'id' => 'a', 'page' => '/', 'type' => 'impression', 'count' => 2 ),
				array( 'day' => self::DAY, 'id' => 'a', 'page' => '/', 'type' => 'click_phone', 'count' => 2 ),
			),
			$rows
		);
	}

	public function test_numeric_ids_from_json_are_accepted() {
		$rows = Batch_Parser::parse( $this->body( array( array( 'id' => 42, 'page' => '/', 'type' => 'impression' ) ) ), self::UA, self::DAY );
		$this->assertSame( '42', $rows[0]['id'] );
	}

	/**
	 * @dataProvider bot_agents
	 */
	public function test_drops_bots( $agent ) {
		$body = $this->body( array( array( 'id' => 'a', 'page' => '/', 'type' => 'impression' ) ) );
		$this->assertSame( array(), Batch_Parser::parse( $body, $agent, self::DAY ) );
	}

	public function bot_agents() {
		return array(
			'googlebot' => array( 'Mozilla/5.0 (compatible; Googlebot/2.1)' ),
			'headless'  => array( 'HeadlessChrome/120' ),
			'curl'      => array( 'curl/8.4.0' ),
			'empty'     => array( '' ),
		);
	}

	public function test_drops_bad_bodies() {
		$this->assertSame( array(), Batch_Parser::parse( 'not json', self::UA, self::DAY ) );
		$this->assertSame( array(), Batch_Parser::parse( '{"items":5}', self::UA, self::DAY ) );
		$this->assertSame( array(), Batch_Parser::parse( '[]', self::UA, self::DAY ) );
	}

	public function test_drops_invalid_fields() {
		$rows = Batch_Parser::parse(
			$this->body(
				array(
					null,
					'string',
					array( 'id' => '<x>', 'page' => '/', 'type' => 'impression' ),
					array( 'id' => array( 'nested' ), 'page' => '/', 'type' => 'impression' ),
					array( 'id' => 'a', 'page' => 'no-slash', 'type' => 'impression' ),
					array( 'id' => 'a', 'page' => '/', 'type' => 'bad type' ),
					array( 'id' => 'a', 'item' => '"', 'page' => '/', 'type' => 'impression' ),
					array( 'id' => 'ok', 'page' => '/p/:id', 'type' => 'click_link' ),
				)
			),
			self::UA,
			self::DAY
		);

		$this->assertSame(
			array( array( 'day' => self::DAY, 'id' => 'ok', 'page' => '/p/:id', 'type' => 'click_link', 'count' => 1 ) ),
			$rows
		);
	}

	public function test_type_allowlist() {
		$rows = Batch_Parser::parse(
			$this->body(
				array(
					array( 'id' => 'a', 'page' => '/', 'type' => 'click_phone' ),
					array( 'id' => 'a', 'page' => '/', 'type' => 'click_other' ),
				)
			),
			self::UA,
			self::DAY,
			array( 'click_phone' )
		);
		$this->assertCount( 1, $rows );
		$this->assertSame( 'click_phone', $rows[0]['type'] );
	}

	public function test_reads_at_most_max_items() {
		$items = array_fill( 0, Batch_Parser::MAX_ITEMS + 50, array( 'id' => 'a', 'page' => '/', 'type' => 'click_link' ) );
		$rows  = Batch_Parser::parse( $this->body( $items ), self::UA, self::DAY );
		$this->assertSame( Batch_Parser::MAX_ITEMS, $rows[0]['count'] );
	}
}
