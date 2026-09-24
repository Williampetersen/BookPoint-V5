<?php
/**
 * Admin staff (agents) and locations API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\LocationRepository;
use PointlyBooking\Repositories\Relations;
use PointlyBooking\Repositories\ScheduleRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Availability\ScheduleResolver;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/agents…, /admin/locations…, /admin/location-categories…
 */
final class StaffController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$read   = self::cap( 'pointlybooking_manage_agents', 'pointlybooking_manage_bookings', 'pointlybooking_manage_settings', 'pointlybooking_manage_services' );
		$manage = self::cap( 'pointlybooking_manage_agents' );

		$this->route(
			'/admin/agents',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'agents_index' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'agents_save' ),
					'permission_callback' => $manage,
				),
			)
		);
		$this->route(
			'/admin/agents-full',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'agents_index' ),
				'permission_callback' => $read,
			)
		);
		$this->route(
			'/admin/agents/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'agents_show' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'agents_save' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'agents_delete' ),
					'permission_callback' => $manage,
				),
			)
		);
		$this->route(
			'/admin/agents/(?P<id>\d+)/services',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'agent_services_get' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'agent_services_set' ),
					'permission_callback' => $manage,
				),
			)
		);
		$this->route(
			'/admin/agents/(?P<id>\d+)/schedule',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'agent_schedule_get' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => array( 'PUT', 'POST' ),
					'callback'            => array( $this, 'agent_schedule_save' ),
					'permission_callback' => $manage,
				),
			)
		);
		$this->route(
			'/admin/agents/(?P<id>\d+)/schedule/copy',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'agent_schedule_copy' ),
				'permission_callback' => $manage,
			)
		);

		$loc = self::cap( 'pointlybooking_manage_settings' );
		$this->route(
			'/admin/locations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'locations_index' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'locations_save' ),
					'permission_callback' => $loc,
				),
			)
		);
		$this->route(
			'/admin/locations/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'locations_show' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'locations_save' ),
					'permission_callback' => $loc,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'locations_delete' ),
					'permission_callback' => $loc,
				),
			)
		);
		$this->route(
			'/admin/locations/(?P<id>\d+)/agents',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'location_agents_get' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => array( 'POST', 'PUT' ),
					'callback'            => array( $this, 'location_agents_set' ),
					'permission_callback' => $loc,
				),
			)
		);
		$this->route(
			'/admin/location-categories',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'location_categories_index' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'location_categories_save' ),
					'permission_callback' => $loc,
				),
			)
		);
		$this->route(
			'/admin/location-categories/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'location_categories_save' ),
					'permission_callback' => $loc,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'location_categories_delete' ),
					'permission_callback' => $loc,
				),
			)
		);
	}

	/* ------------------------------------------------------------------ Agents */

	/**
	 * Staff list.
	 *
	 * @return \WP_REST_Response
	 */
	public function agents_index() {
		$map = Relations::map( 'agent_services', 'agent_id' );
		$out = array();
		foreach ( AgentRepository::all() as $a ) {
			$row                    = Presenter::agent( $a, $map[ $a['id'] ] ?? array() );
			$row['services_count']  = count( $row['service_ids'] );
			$row['has_own_hours']   = ScheduleRepository::agent_has_schedule( $a['id'] ) || '' !== $row['schedule_json'];
			$out[]                  = $row;
		}
		return $this->ok( $out );
	}

	/**
	 * One staff member.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function agents_show( \WP_REST_Request $r ) {
		$a = AgentRepository::find( (int) $r['id'] );
		if ( ! $a ) {
			return $this->error( 'not_found', __( 'Staff member not found.', 'pointly-booking' ), 404 );
		}
		return $this->ok( Presenter::agent( $a, Relations::ids( 'agent_services', 'agent_id', $a['id'] ) ) );
	}

	/**
	 * Create or update a staff member.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function agents_save( \WP_REST_Request $r ) {
		$id   = (int) ( $r['id'] ?? 0 );
		$body = $this->body( $r );
		$data = array();
		foreach ( array( 'first_name', 'last_name' ) as $key ) {
			if ( array_key_exists( $key, $body ) ) {
				$data[ $key ] = Sanitize::text( $body[ $key ], 191 );
			}
		}
		if ( ! $id && '' === trim( ( $data['first_name'] ?? '' ) . ( $data['last_name'] ?? '' ) ) ) {
			if ( ! empty( $body['name'] ) ) {
				$parts              = preg_split( '/\s+/', Sanitize::text( $body['name'], 191 ), 2 );
				$data['first_name'] = $parts[0];
				$data['last_name']  = $parts[1] ?? '';
			} else {
				return $this->error( 'invalid_name', __( 'Please enter a name.', 'pointly-booking' ), 400, array( 'field' => 'first_name' ) );
			}
		}
		if ( array_key_exists( 'email', $body ) ) {
			$data['email'] = Sanitize::email( $body['email'] );
			if ( '' === $data['email'] && '' !== trim( (string) $body['email'] ) ) {
				return $this->error( 'invalid_email', __( 'Please enter a valid email address.', 'pointly-booking' ), 400, array( 'field' => 'email' ) );
			}
		}
		if ( array_key_exists( 'phone', $body ) ) {
			$data['phone'] = Sanitize::text( $body['phone'], 50 );
		}
		if ( array_key_exists( 'image_id', $body ) ) {
			$data['image_id'] = absint( $body['image_id'] );
		}
		if ( array_key_exists( 'is_active', $body ) ) {
			$data['is_active'] = Sanitize::bool01( $body['is_active'] );
		}
		if ( array_key_exists( 'schedule_json', $body ) ) {
			$json = is_array( $body['schedule_json'] ) ? wp_json_encode( $body['schedule_json'] ) : trim( (string) $body['schedule_json'] );
			if ( '' !== $json && null === ScheduleResolver::parse_weekday_json( $json ) ) {
				return $this->error( 'invalid_schedule', __( 'The working hours are not valid.', 'pointly-booking' ), 400, array( 'field' => 'schedule_json' ) );
			}
			$data['schedule_json'] = '' !== $json ? $json : null;
		}

		$created = ! $id;
		if ( $id ) {
			if ( ! AgentRepository::find( $id ) ) {
				return $this->error( 'not_found', __( 'Staff member not found.', 'pointly-booking' ), 404 );
			}
			AgentRepository::save( $id, $data );
		} else {
			$id = AgentRepository::create( array_merge( array( 'is_active' => 1 ), $data ) );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The staff member could not be saved.', 'pointly-booking' ), 500 );
			}
		}
		if ( array_key_exists( 'service_ids', $body ) ) {
			Relations::set( 'agent_services', 'agent_id', $id, Sanitize::ids( $body['service_ids'] ) );
			Cache::bump( 'catalog' );
			Cache::bump( 'availability' );
		}
		if ( isset( $body['schedule'] ) && is_array( $body['schedule'] ) ) {
			ScheduleRepository::replace_weekly( $id, self::clean_week( $body['schedule'] ) );
		}
		if ( isset( $body['breaks'] ) && is_array( $body['breaks'] ) ) {
			ScheduleRepository::replace_agent_breaks( $id, $body['breaks'] );
		}

		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$response      = $this->agents_show( $request );
		if ( $created ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Delete a staff member.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function agents_delete( \WP_REST_Request $r ) {
		AgentRepository::remove( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * 2.x: GET /admin/agents/{id}/services.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function agent_services_get( \WP_REST_Request $r ) {
		return $this->ok( Relations::ids( 'agent_services', 'agent_id', (int) $r['id'] ) );
	}

	/**
	 * 2.x: PUT /admin/agents/{id}/services.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function agent_services_set( \WP_REST_Request $r ) {
		$ids = Sanitize::ids( $this->body( $r )['service_ids'] ?? array() );
		Relations::set( 'agent_services', 'agent_id', (int) $r['id'], $ids );
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
		return $this->ok(
			array(
				'saved'       => true,
				'service_ids' => $ids,
			)
		);
	}

	/**
	 * Normalises a week payload: weekday (1-7) => [ {start,end,breaks,is_enabled} ].
	 *
	 * @param array $week Raw week.
	 * @return array
	 */
	public static function clean_week( array $week ) {
		$out = array();
		foreach ( $week as $day => $intervals ) {
			$day = (int) $day;
			if ( $day < 1 || $day > 7 || ! is_array( $intervals ) ) {
				continue;
			}
			foreach ( $intervals as $interval ) {
				if ( is_array( $interval ) ) {
					$out[ $day ][] = array(
						'start'      => $interval['start'] ?? ( $interval['start_time'] ?? '' ),
						'end'        => $interval['end'] ?? ( $interval['end_time'] ?? '' ),
						'breaks'     => $interval['breaks'] ?? array(),
						'is_enabled' => ! isset( $interval['is_enabled'] ) || ! empty( $interval['is_enabled'] ),
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Staff hours: own weekly schedule (if any) plus dated time off.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function agent_schedule_get( \WP_REST_Request $r ) {
		$id    = (int) $r['id'];
		$agent = AgentRepository::find( $id );
		if ( ! $agent ) {
			return $this->error( 'not_found', __( 'Staff member not found.', 'pointly-booking' ), 404 );
		}
		$own      = ScheduleRepository::weekly( $id );
		$override = $agent['schedule_json'] ? ScheduleResolver::parse_weekday_json( $agent['schedule_json'] ) : null;
		$mode     = $own ? 'custom' : ( $override ? 'custom' : 'business' );
		return $this->ok(
			array(
				'agent_id' => $id,
				'mode'     => $mode,
				'schedule' => $own ? $own : ( $override ? $override : ScheduleRepository::weekly( 0 ) ),
				'breaks'   => ScheduleRepository::agent_breaks( $id ),
				// 2.x shape.
				'hours'    => $own,
			)
		);
	}

	/**
	 * Saves staff hours. mode=business removes the staff member's own hours.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function agent_schedule_save( \WP_REST_Request $r ) {
		$id   = (int) $r['id'];
		$body = $this->body( $r );
		if ( ! AgentRepository::find( $id ) ) {
			return $this->error( 'not_found', __( 'Staff member not found.', 'pointly-booking' ), 404 );
		}
		$mode = $body['mode'] ?? ( isset( $body['hours'] ) || isset( $body['schedule'] ) ? 'custom' : 'business' );
		if ( 'business' === $mode ) {
			ScheduleRepository::replace_weekly( $id, array() );
			AgentRepository::save( $id, array( 'schedule_json' => null ) );
		} else {
			$week = self::clean_week( (array) ( $body['schedule'] ?? ( $body['hours'] ?? array() ) ) );
			if ( ! ScheduleRepository::replace_weekly( $id, $week ) ) {
				return $this->error( 'save_failed', __( 'The working hours could not be saved.', 'pointly-booking' ), 500 );
			}
			AgentRepository::save( $id, array( 'schedule_json' => null ) );
		}
		if ( isset( $body['breaks'] ) && is_array( $body['breaks'] ) ) {
			ScheduleRepository::replace_agent_breaks( $id, $body['breaks'] );
		}
		return $this->agent_schedule_get( $r );
	}

	/**
	 * Copies the business hours into the staff member's own schedule.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function agent_schedule_copy( \WP_REST_Request $r ) {
		$global = ScheduleRepository::weekly( 0 );
		ScheduleRepository::replace_weekly( (int) $r['id'], $global );
		$count = 0;
		foreach ( $global as $intervals ) {
			$count += count( $intervals );
		}
		return $this->ok( array( 'copied' => $count ) );
	}

	/* --------------------------------------------------------------- Locations */

	/**
	 * Locations with staff assignments.
	 *
	 * @return \WP_REST_Response
	 */
	public function locations_index() {
		$assignments = LocationRepository::all_assignments();
		$categories  = array();
		foreach ( LocationRepository::categories() as $category ) {
			$categories[ $category['id'] ] = $category['name'];
		}
		$out = array();
		foreach ( LocationRepository::all() as $l ) {
			$row                  = Presenter::location( $l );
			$row['agents']        = $assignments[ $l['id'] ] ?? array();
			$row['category_name'] = $categories[ $l['category_id'] ] ?? '';
			$out[]                = $row;
		}
		return $this->ok( $out );
	}

	/**
	 * One location.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function locations_show( \WP_REST_Request $r ) {
		$l = LocationRepository::find( (int) $r['id'] );
		if ( ! $l ) {
			return $this->error( 'not_found', __( 'Location not found.', 'pointly-booking' ), 404 );
		}
		$row           = Presenter::location( $l );
		$row['agents'] = LocationRepository::agents( $l['id'] );
		return $this->ok( $row );
	}

	/**
	 * Create or update a location.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function locations_save( \WP_REST_Request $r ) {
		$id   = (int) ( $r['id'] ?? 0 );
		$body = $this->body( $r );
		$data = array();
		if ( array_key_exists( 'name', $body ) || ! $id ) {
			$data['name'] = Sanitize::text( $body['name'] ?? '', 190 );
			if ( '' === $data['name'] ) {
				return $this->error( 'invalid_name', __( 'Please enter a location name.', 'pointly-booking' ), 400, array( 'field' => 'name' ) );
			}
		}
		if ( array_key_exists( 'address', $body ) ) {
			$data['address'] = Sanitize::text( $body['address'], 255 );
		}
		if ( array_key_exists( 'category_id', $body ) ) {
			$data['category_id'] = absint( $body['category_id'] ) ? absint( $body['category_id'] ) : null;
		}
		if ( array_key_exists( 'image_id', $body ) ) {
			$data['image_id'] = absint( $body['image_id'] ) ? absint( $body['image_id'] ) : null;
		}
		if ( array_key_exists( 'status', $body ) || array_key_exists( 'is_active', $body ) ) {
			$active         = array_key_exists( 'status', $body ) ? 'inactive' !== $body['status'] : (bool) Sanitize::bool01( $body['is_active'] );
			$data['status'] = $active ? 'active' : 'inactive';
		}
		if ( array_key_exists( 'use_custom_schedule', $body ) ) {
			$data['use_custom_schedule'] = Sanitize::bool01( $body['use_custom_schedule'] );
		}
		if ( array_key_exists( 'schedule', $body ) ) {
			$rows = array();
			foreach ( (array) $body['schedule'] as $row ) {
				$start = Sanitize::time( $row['start'] ?? '' );
				$end   = Sanitize::time( $row['end'] ?? '' );
				$day   = (int) ( $row['day'] ?? -1 );
				if ( $day >= 0 && $day <= 6 && '' !== $start && '' !== $end && $end > $start ) {
					$rows[] = array(
						'day'   => $day,
						'start' => $start,
						'end'   => $end,
					);
				}
			}
			$data['schedule_json'] = $rows ? wp_json_encode( $rows ) : null;
		}
		$created = ! $id;
		if ( $id ) {
			if ( ! LocationRepository::find( $id ) ) {
				return $this->error( 'not_found', __( 'Location not found.', 'pointly-booking' ), 404 );
			}
			LocationRepository::save( $id, $data );
		} else {
			$id = LocationRepository::create( array_merge( array( 'status' => 'active' ), $data ) );
		}
		if ( isset( $body['agents'] ) && is_array( $body['agents'] ) ) {
			LocationRepository::set_agents( $id, $body['agents'] );
		}
		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$response      = $this->locations_show( $request );
		if ( $created ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Delete a location.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function locations_delete( \WP_REST_Request $r ) {
		LocationRepository::remove( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * Staff assignments of a location.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function location_agents_get( \WP_REST_Request $r ) {
		return $this->ok( LocationRepository::agents( (int) $r['id'] ) );
	}

	/**
	 * Replaces staff assignments.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function location_agents_set( \WP_REST_Request $r ) {
		LocationRepository::set_agents( (int) $r['id'], (array) ( $this->body( $r )['agents'] ?? array() ) );
		return $this->ok( array( 'saved' => true ) );
	}

	/**
	 * Location categories.
	 *
	 * @return \WP_REST_Response
	 */
	public function location_categories_index() {
		return $this->ok( LocationRepository::categories() );
	}

	/**
	 * Create or update a location category.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function location_categories_save( \WP_REST_Request $r ) {
		$body = $this->body( $r );
		$name = Sanitize::text( $body['name'] ?? '', 190 );
		$id   = (int) ( $r['id'] ?? 0 );
		if ( '' === $name && ! $id ) {
			return $this->error( 'invalid_name', __( 'Please enter a name.', 'pointly-booking' ), 400, array( 'field' => 'name' ) );
		}
		$data = array();
		if ( '' !== $name ) {
			$data['name'] = $name;
		}
		if ( array_key_exists( 'image_id', $body ) ) {
			$data['image_id'] = absint( $body['image_id'] ) ? absint( $body['image_id'] ) : null;
		}
		$id = LocationRepository::save_category( $id, $data );
		foreach ( LocationRepository::categories() as $category ) {
			if ( $category['id'] === $id ) {
				return $this->ok( $category, $r['id'] ? 200 : 201 );
			}
		}
		return $this->ok( array( 'id' => $id ) );
	}

	/**
	 * Delete a location category.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function location_categories_delete( \WP_REST_Request $r ) {
		LocationRepository::delete_category( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}
}
