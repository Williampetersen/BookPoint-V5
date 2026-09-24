<?php
/**
 * Base REST controller.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers: namespace, responses, permissions and argument schemas.
 */
abstract class Controller {

	const NS = 'pointly-booking/v1';

	/**
	 * Registers the controller's routes.
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Registers a route in the plugin namespace.
	 *
	 * @param string $path      Route path.
	 * @param array  $endpoints Endpoint definitions.
	 * @return void
	 */
	protected function route( $path, array $endpoints ) {
		register_rest_route( self::NS, $path, $endpoints );
	}

	/**
	 * Success envelope (kept from 2.x: { status: "success", data: … }).
	 *
	 * @param mixed $data   Payload.
	 * @param int   $status HTTP status.
	 * @return \WP_REST_Response
	 */
	protected function ok( $data = null, $status = 200 ) {
		return new \WP_REST_Response(
			array(
				'status' => 'success',
				'data'   => $data,
			),
			$status
		);
	}

	/**
	 * Error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Human message.
	 * @param int    $status  HTTP status.
	 * @param array  $extra   Extra data (e.g. field).
	 * @return \WP_Error
	 */
	protected function error( $code, $message, $status = 400, array $extra = array() ) {
		return new \WP_Error( $code, $message, array_merge( array( 'status' => $status ), $extra ) );
	}

	/**
	 * Returns $result as-is when it is an error, otherwise wraps it.
	 *
	 * @param mixed $result Result or WP_Error.
	 * @param int   $status HTTP status on success.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function respond( $result, $status = 200 ) {
		return is_wp_error( $result ) ? $result : $this->ok( $result, $status );
	}

	/**
	 * Permission callback requiring a capability (site administrators always pass).
	 *
	 * @param string ...$caps Any of these capabilities.
	 * @return callable
	 */
	public static function cap( ...$caps ) {
		return static function () use ( $caps ) {
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
			foreach ( $caps as $cap ) {
				if ( current_user_can( $cap ) ) {
					return true;
				}
			}
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'pointly-booking' ), array( 'status' => rest_authorization_required_code() ) );
		};
	}

	/**
	 * Public endpoints (no authentication; data is validated per request).
	 *
	 * @return bool
	 */
	public static function public_access() {
		return true;
	}

	/**
	 * Common argument definitions.
	 *
	 * @param string $type     Type key.
	 * @param bool   $required Required.
	 * @param array  $extra    Extra schema keys.
	 * @return array
	 */
	protected static function arg( $type, $required = false, array $extra = array() ) {
		$map             = array(
			'id'     => array(
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			),
			'int'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
			),
			'bool'   => array(
				'type' => 'boolean',
			),
			'string' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'text'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'date'   => array(
				'type'    => 'string',
				'pattern' => '^\d{4}-\d{2}-\d{2}$',
			),
			'month'  => array(
				'type'    => 'string',
				'pattern' => '^\d{4}-\d{2}$',
			),
			'time'   => array(
				'type'    => 'string',
				'pattern' => '^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$',
			),
			'email'  => array(
				'type'   => 'string',
				'format' => 'email',
			),
			'key'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'ids'    => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
			'object' => array(
				'type' => 'object',
			),
			'array'  => array(
				'type' => 'array',
			),
		);
		$def             = $map[ $type ] ?? array( 'type' => 'string' );
		$def['required'] = $required;
		return array_merge( $def, $extra );
	}

	/**
	 * Paging args.
	 *
	 * @return array
	 */
	protected static function paging_args() {
		return array(
			'page'     => self::arg( 'id', false, array( 'default' => 1 ) ),
			'per_page' => self::arg(
				'id',
				false,
				array(
					'default' => 20,
					'maximum' => 200,
				)
			),
			'search'   => self::arg( 'string' ),
			'orderby'  => self::arg( 'key' ),
			'order'    => self::arg( 'string', false, array( 'enum' => array( 'asc', 'desc', 'ASC', 'DESC' ) ) ),
		);
	}

	/**
	 * JSON body as array.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	protected function body( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		return is_array( $body ) ? $body : array();
	}
}
