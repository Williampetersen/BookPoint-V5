<?php
/**
 * Gutenberg block "bookpoint/booking-form" (name kept from 2.x so saved posts keep working).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block from build/blocks/booking-form/block.json and renders it on the server.
 */
final class Block {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Registers the block type.
	 *
	 * @return void
	 */
	public static function register_block() {
		$dir = POINTLYBOOKING_PLUGIN_DIR . 'build/blocks/booking-form';
		if ( ! is_readable( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type(
			$dir,
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
		wp_set_script_translations( 'bookpoint-booking-form-editor-script', 'pointly-booking', POINTLYBOOKING_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Server render: the same markup as the shortcode.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$a       = is_array( $attributes ) ? $attributes : array();
		$html    = Shortcodes::booking_form(
			array(
				'label'         => $a['label'] ?? __( 'Book now', 'pointly-booking' ),
				'display'       => $a['display'] ?? 'button',
				'service_id'    => $a['serviceId'] ?? 0,
				'category_id'   => $a['categoryId'] ?? 0,
				'agent_id'      => $a['agentId'] ?? 0,
				'location_id'   => $a['locationId'] ?? 0,
				'default_date'  => $a['defaultDate'] ?? '',
				'hide_notes'    => ! empty( $a['hideNotes'] ) ? 1 : 0,
				'require_phone' => ! empty( $a['requirePhone'] ) ? 1 : 0,
				'compact'       => ! empty( $a['compact'] ) ? 1 : 0,
			)
		);
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}
}
