<?php
/**
 * Admin services, categories and extras API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\CategoryRepository;
use PointlyBooking\Repositories\ExtraRepository;
use PointlyBooking\Repositories\Relations;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Availability\ScheduleResolver;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Money;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/services…, /admin/categories…, /admin/extras…
 */
final class CatalogController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$read   = self::cap( 'pointlybooking_manage_services', 'pointlybooking_manage_bookings', 'pointlybooking_manage_settings' );
		$manage = self::cap( 'pointlybooking_manage_services' );

		foreach ( array( 'services', 'categories', 'extras' ) as $type ) {
			$this->route(
				'/admin/' . $type,
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( $this, $type . '_index' ),
						'permission_callback' => $read,
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $type . '_save' ),
						'permission_callback' => $manage,
					),
				)
			);
			$this->route(
				'/admin/' . $type . '/(?P<id>\d+)',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( $this, $type . '_show' ),
						'permission_callback' => $read,
					),
					array(
						'methods'             => \WP_REST_Server::EDITABLE,
						'callback'            => array( $this, $type . '_save' ),
						'permission_callback' => $manage,
					),
					array(
						'methods'             => \WP_REST_Server::DELETABLE,
						'callback'            => array( $this, $type . '_delete' ),
						'permission_callback' => $manage,
					),
				)
			);
			$this->route(
				'/admin/' . $type . '/reorder',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reorder' ),
					'permission_callback' => $manage,
					'args'                => array( 'ids' => self::arg( 'ids', true ) ),
				)
			);
		}

		// 2.x relation endpoints.
		$this->route(
			'/admin/services/(?P<id>\d+)/categories',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'service_categories_get' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'service_categories_set' ),
					'permission_callback' => $manage,
				),
			)
		);
		$this->route(
			'/admin/extras/(?P<id>\d+)/services',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'extra_services_get' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'extra_services_set' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	/* ---------------------------------------------------------------- Services */

	/**
	 * Services with relations.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function services_index( \WP_REST_Request $r ) {
		$search     = strtolower( trim( (string) ( $r->get_param( 'search' ) ?? $r->get_param( 'q' ) ?? '' ) ) );
		$categories = Relations::map( 'service_categories', 'service_id' );
		$agents     = Relations::map( 'agent_services', 'service_id' );
		$extras     = Relations::map( 'extra_services', 'service_id' );
		$counts     = ServiceRepository::booking_counts();
		$out        = array();
		foreach ( ServiceRepository::all() as $s ) {
			if ( '' !== $search && false === strpos( strtolower( $s['name'] ), $search ) ) {
				continue;
			}
			$row                   = Presenter::service( $s, $categories[ $s['id'] ] ?? array(), $agents[ $s['id'] ] ?? array(), $extras[ $s['id'] ] ?? array() );
			$row['bookings_count'] = $counts[ $s['id'] ] ?? 0;
			$out[]                 = $row;
		}
		return $this->ok( $out );
	}

	/**
	 * One service.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function services_show( \WP_REST_Request $r ) {
		$s = ServiceRepository::find( (int) $r['id'] );
		if ( ! $s ) {
			return $this->error( 'not_found', __( 'Service not found.', 'pointly-booking' ), 404 );
		}
		return $this->ok(
			Presenter::service(
				$s,
				Relations::ids( 'service_categories', 'service_id', $s['id'] ),
				Relations::ids( 'agent_services', 'service_id', $s['id'] ),
				Relations::ids( 'extra_services', 'service_id', $s['id'] )
			)
		);
	}

	/**
	 * Create or update a service (single request saves relations too).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function services_save( \WP_REST_Request $r ) {
		$id       = (int) ( $r['id'] ?? 0 );
		$body     = $this->body( $r );
		$existing = $id ? ServiceRepository::find( $id ) : null;
		if ( $id && ! $existing ) {
			return $this->error( 'not_found', __( 'Service not found.', 'pointly-booking' ), 404 );
		}

		$data = array();
		if ( array_key_exists( 'name', $body ) || ! $id ) {
			$data['name'] = Sanitize::text( $body['name'] ?? '', 190 );
			if ( '' === $data['name'] ) {
				return $this->error( 'invalid_name', __( 'Please enter a service name.', 'pointly-booking' ), 400, array( 'field' => 'name' ) );
			}
		}
		if ( array_key_exists( 'description', $body ) ) {
			$data['description'] = Sanitize::html( $body['description'] );
		}
		if ( array_key_exists( 'duration_minutes', $body ) || array_key_exists( 'duration', $body ) ) {
			$data['duration_minutes'] = Sanitize::int_range( $body['duration_minutes'] ?? $body['duration'], 5, 1440 );
		}
		if ( array_key_exists( 'price', $body ) || array_key_exists( 'price_cents', $body ) ) {
			$data['price_cents'] = array_key_exists( 'price_cents', $body ) ? max( 0, (int) $body['price_cents'] ) : (int) round( Sanitize::money( $body['price'] ) * 100 );
		}
		if ( array_key_exists( 'is_active', $body ) ) {
			$data['is_active'] = Sanitize::bool01( $body['is_active'] );
		}
		if ( array_key_exists( 'image_id', $body ) ) {
			$data['image_id'] = absint( $body['image_id'] );
		}
		if ( array_key_exists( 'sort_order', $body ) ) {
			$data['sort_order'] = (int) $body['sort_order'];
		}
		foreach ( array( 'buffer_before_minutes' => 'buffer_before', 'buffer_after_minutes' => 'buffer_after' ) as $key => $legacy ) {
			if ( array_key_exists( $key, $body ) || array_key_exists( $legacy, $body ) ) {
				$data[ $key ] = Sanitize::int_range( $body[ $key ] ?? $body[ $legacy ], 0, 240 );
			}
		}
		if ( array_key_exists( 'capacity', $body ) ) {
			$data['capacity'] = Sanitize::int_range( $body['capacity'], 1, 100 );
		}
		if ( array_key_exists( 'use_global_schedule', $body ) ) {
			$data['use_global_schedule'] = Sanitize::bool01( $body['use_global_schedule'] );
		}
		if ( array_key_exists( 'schedule_json', $body ) ) {
			$json = is_array( $body['schedule_json'] ) ? wp_json_encode( $body['schedule_json'] ) : (string) $body['schedule_json'];
			if ( '' !== trim( $json ) && null === ScheduleResolver::parse_weekday_json( $json ) ) {
				return $this->error( 'invalid_schedule', __( 'The custom hours are not valid.', 'pointly-booking' ), 400, array( 'field' => 'schedule_json' ) );
			}
			$data['schedule_json'] = '' !== trim( $json ) ? $json : null;
		}

		if ( $id ) {
			ServiceRepository::save( $id, $data );
		} else {
			$data = array_merge(
				array(
					'duration_minutes' => 60,
					'price_cents'      => 0,
					'currency'         => Money::currency(),
					'capacity'         => 1,
				),
				$data
			);
			$id   = ServiceRepository::create( $data );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The service could not be saved.', 'pointly-booking' ), 500 );
			}
		}

		if ( array_key_exists( 'category_ids', $body ) ) {
			$ids = Sanitize::ids( $body['category_ids'] );
			Relations::set( 'service_categories', 'service_id', $id, $ids );
			ServiceRepository::save( $id, array( 'category_id' => $ids ? $ids[0] : null ) );
		}
		if ( array_key_exists( 'agent_ids', $body ) ) {
			Relations::set( 'agent_services', 'service_id', $id, Sanitize::ids( $body['agent_ids'] ) );
		}
		if ( array_key_exists( 'extra_ids', $body ) ) {
			Relations::set( 'extra_services', 'service_id', $id, Sanitize::ids( $body['extra_ids'] ) );
		}
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );

		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$response      = $this->services_show( $request );
		if ( ! $existing ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Delete a service.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function services_delete( \WP_REST_Request $r ) {
		ServiceRepository::remove( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/* -------------------------------------------------------------- Categories */

	/**
	 * Categories with service counts.
	 *
	 * @return \WP_REST_Response
	 */
	public function categories_index() {
		$map = Relations::map( 'service_categories', 'category_id' );
		return $this->ok(
			array_map(
				static function ( $c ) use ( $map ) {
					return Presenter::category( $c, count( $map[ $c['id'] ] ?? array() ) );
				},
				CategoryRepository::all()
			)
		);
	}

	/**
	 * One category.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function categories_show( \WP_REST_Request $r ) {
		$c = CategoryRepository::find( (int) $r['id'] );
		if ( ! $c ) {
			return $this->error( 'not_found', __( 'Category not found.', 'pointly-booking' ), 404 );
		}
		$row                = Presenter::category( $c, count( Relations::ids( 'service_categories', 'category_id', $c['id'] ) ) );
		$row['service_ids'] = Relations::ids( 'service_categories', 'category_id', $c['id'] );
		return $this->ok( $row );
	}

	/**
	 * Create or update a category.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function categories_save( \WP_REST_Request $r ) {
		$id   = (int) ( $r['id'] ?? 0 );
		$body = $this->body( $r );
		$data = array();
		if ( array_key_exists( 'name', $body ) || ! $id ) {
			$data['name'] = Sanitize::text( $body['name'] ?? '', 190 );
			if ( '' === $data['name'] ) {
				return $this->error( 'invalid_name', __( 'Please enter a category name.', 'pointly-booking' ), 400, array( 'field' => 'name' ) );
			}
		}
		if ( array_key_exists( 'description', $body ) ) {
			$data['description'] = Sanitize::textarea( $body['description'] );
		}
		if ( array_key_exists( 'image_id', $body ) ) {
			$data['image_id'] = absint( $body['image_id'] );
		}
		if ( array_key_exists( 'sort_order', $body ) ) {
			$data['sort_order'] = (int) $body['sort_order'];
		}
		if ( array_key_exists( 'is_active', $body ) ) {
			$data['is_active'] = Sanitize::bool01( $body['is_active'] );
		}
		$created = ! $id;
		if ( $id ) {
			if ( ! CategoryRepository::find( $id ) ) {
				return $this->error( 'not_found', __( 'Category not found.', 'pointly-booking' ), 404 );
			}
			CategoryRepository::save( $id, $data );
		} else {
			$id = CategoryRepository::create( $data );
		}
		if ( array_key_exists( 'service_ids', $body ) ) {
			$wanted  = Sanitize::ids( $body['service_ids'] );
			$current = Relations::ids( 'service_categories', 'category_id', $id );
			foreach ( array_diff( $wanted, $current ) as $service_id ) {
				Relations::set( 'service_categories', 'service_id', $service_id, array_merge( Relations::ids( 'service_categories', 'service_id', $service_id ), array( $id ) ) );
			}
			foreach ( array_diff( $current, $wanted ) as $service_id ) {
				Relations::set( 'service_categories', 'service_id', $service_id, array_diff( Relations::ids( 'service_categories', 'service_id', $service_id ), array( $id ) ) );
			}
			Cache::bump( 'catalog' );
		}
		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$response      = $this->categories_show( $request );
		if ( $created ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Delete a category.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function categories_delete( \WP_REST_Request $r ) {
		CategoryRepository::remove( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/* ------------------------------------------------------------------ Extras */

	/**
	 * Extras with linked services.
	 *
	 * @return \WP_REST_Response
	 */
	public function extras_index() {
		$map = Relations::map( 'extra_services', 'extra_id' );
		return $this->ok(
			array_map(
				static function ( $e ) use ( $map ) {
					return Presenter::extra( $e, $map[ $e['id'] ] ?? array() );
				},
				ExtraRepository::all()
			)
		);
	}

	/**
	 * One extra.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function extras_show( \WP_REST_Request $r ) {
		$e = ExtraRepository::find( (int) $r['id'] );
		if ( ! $e ) {
			return $this->error( 'not_found', __( 'Extra not found.', 'pointly-booking' ), 404 );
		}
		return $this->ok( Presenter::extra( $e, Relations::ids( 'extra_services', 'extra_id', $e['id'] ) ) );
	}

	/**
	 * Create or update an extra.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function extras_save( \WP_REST_Request $r ) {
		$id   = (int) ( $r['id'] ?? 0 );
		$body = $this->body( $r );
		$data = array();
		if ( array_key_exists( 'name', $body ) || ! $id ) {
			$data['name'] = Sanitize::text( $body['name'] ?? '', 190 );
			if ( '' === $data['name'] ) {
				return $this->error( 'invalid_name', __( 'Please enter a name.', 'pointly-booking' ), 400, array( 'field' => 'name' ) );
			}
		}
		if ( array_key_exists( 'description', $body ) ) {
			$data['description'] = Sanitize::textarea( $body['description'] );
		}
		if ( array_key_exists( 'price', $body ) ) {
			$data['price'] = Sanitize::money( $body['price'] );
		}
		if ( array_key_exists( 'duration_min', $body ) ) {
			$data['duration_min'] = Sanitize::int_range( $body['duration_min'], 0, 1440 );
		}
		if ( array_key_exists( 'image_id', $body ) ) {
			$data['image_id'] = absint( $body['image_id'] );
		}
		if ( array_key_exists( 'sort_order', $body ) ) {
			$data['sort_order'] = (int) $body['sort_order'];
		}
		if ( array_key_exists( 'is_active', $body ) ) {
			$data['is_active'] = Sanitize::bool01( $body['is_active'] );
		}
		$created = ! $id;
		if ( $id ) {
			if ( ! ExtraRepository::find( $id ) ) {
				return $this->error( 'not_found', __( 'Extra not found.', 'pointly-booking' ), 404 );
			}
			ExtraRepository::save( $id, $data );
		} else {
			$id = ExtraRepository::create( $data );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The extra could not be saved.', 'pointly-booking' ), 500 );
			}
		}
		if ( array_key_exists( 'service_ids', $body ) ) {
			ExtraRepository::set_services( $id, Sanitize::ids( $body['service_ids'] ) );
		}
		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$response      = $this->extras_show( $request );
		if ( $created ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Delete an extra.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function extras_delete( \WP_REST_Request $r ) {
		ExtraRepository::remove( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * Reorders services/categories/extras by the given ID order.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function reorder( \WP_REST_Request $r ) {
		$ids   = Sanitize::ids( $r->get_param( 'ids' ) );
		$route = $r->get_route();
		$repo  = false !== strpos( $route, '/categories/' ) ? CategoryRepository::class : ( false !== strpos( $route, '/extras/' ) ? ExtraRepository::class : ServiceRepository::class );
		$pos   = 0;
		foreach ( $ids as $id ) {
			$repo::update( $id, array( 'sort_order' => ++$pos ) );
		}
		Cache::bump( 'catalog' );
		return $this->ok( array( 'reordered' => count( $ids ) ) );
	}

	/**
	 * 2.x: GET /admin/services/{id}/categories.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function service_categories_get( \WP_REST_Request $r ) {
		return $this->ok( Relations::ids( 'service_categories', 'service_id', (int) $r['id'] ) );
	}

	/**
	 * 2.x: PUT /admin/services/{id}/categories.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function service_categories_set( \WP_REST_Request $r ) {
		$ids = Sanitize::ids( $this->body( $r )['category_ids'] ?? array() );
		Relations::set( 'service_categories', 'service_id', (int) $r['id'], $ids );
		Cache::bump( 'catalog' );
		return $this->ok(
			array(
				'saved'        => true,
				'category_ids' => $ids,
			)
		);
	}

	/**
	 * 2.x: GET /admin/extras/{id}/services.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function extra_services_get( \WP_REST_Request $r ) {
		return $this->ok( Relations::ids( 'extra_services', 'extra_id', (int) $r['id'] ) );
	}

	/**
	 * 2.x: PUT /admin/extras/{id}/services.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function extra_services_set( \WP_REST_Request $r ) {
		$ids = Sanitize::ids( $this->body( $r )['service_ids'] ?? array() );
		ExtraRepository::set_services( (int) $r['id'], $ids );
		return $this->ok(
			array(
				'saved'       => true,
				'service_ids' => $ids,
			)
		);
	}
}
