<?php
/**
 * Tags each post in a Query Loop block so block themes work with no
 * template changes.
 *
 * @package Beacon_Tracker
 */

namespace Beacon_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Post template support.
 */
class Post_Template {

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_filter( 'render_block_core/post-template', array( $this, 'add_attributes' ) );
	}

	/**
	 * Add data-track-id="{post ID}" to every post in the loop. The post ID
	 * comes from the post-{ID} class core already puts on each item.
	 *
	 * @param string $html Rendered block.
	 * @return string
	 */
	public function add_attributes( $html ) {
		/**
		 * Whether to tag posts in Query Loop blocks.
		 *
		 * @param bool $enabled
		 */
		if ( ! apply_filters( 'beacon_tracker_tag_query_loops', true ) ) {
			return $html;
		}

		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'class_name' => 'wp-block-post' ) ) ) {
			if ( null !== $tags->get_attribute( 'data-track-id' ) ) {
				continue;
			}
			if ( preg_match( '/(?:^|\s)post-(\d+)(?:\s|$)/', (string) $tags->get_attribute( 'class' ), $match ) ) {
				$tags->set_attribute( 'data-track-id', $match[1] );
			}
		}
		return $tags->get_updated_html();
	}
}
