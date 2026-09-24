<?php
/**
 * Admin promo codes API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\PromoCodeRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/promo-codes…
 */
final class PromoCodesController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$can = self::cap( 'pointlybooking_manage_settings', 'pointlybooking_manage_services' );

		$this->route(
			'/admin/promo-codes',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $can,
					'args'                => array( 'search' => self::arg( 'string' ) ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			'/admin/promo-codes/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			'/admin/promo-codes/(?P<id>\d+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * All codes.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $r ) {
		return $this->ok( PromoCodeRepository::all_codes( (string) $r->get_param( 'search' ) ) );
	}

	/**
	 * One code.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $r ) {
		$promo = PromoCodeRepository::find( (int) $r['id'] );
		return $promo ? $this->ok( $promo ) : $this->error( 'not_found', __( 'Promo code not found.', 'pointly-booking' ), 404 );
	}

	/**
	 * Create or update a code.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $r ) {
		$id       = (int) ( $r['id'] ?? 0 );
		$body     = $this->body( $r );
		$existing = $id ? PromoCodeRepository::find( $id ) : null;
		if ( $id && ! $existing ) {
			return $this->error( 'not_found', __( 'Promo code not found.', 'pointly-booking' ), 404 );
		}
		$merged = array_merge( $existing ? $existing : array(), $body );

		$code = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $merged['code'] ?? '' ) ) );
		if ( strlen( $code ) < 2 || strlen( $code ) > 60 ) {
			return $this->error( 'invalid_code', __( 'Use 2–60 letters, numbers, dashes or underscores.', 'pointly-booking' ), 400, array( 'field' => 'code' ) );
		}
		$other = PromoCodeRepository::find_by_code( $code );
		if ( $other && $other['id'] !== $id ) {
			return $this->error( 'duplicate_code', __( 'This code already exists.', 'pointly-booking' ), 409, array( 'field' => 'code' ) );
		}

		$type   = Sanitize::one_of( $merged['type'] ?? 'percent', array( 'percent', 'fixed' ), 'percent' );
		$amount = Sanitize::money( $merged['amount'] ?? 0 );
		if ( $amount <= 0 || ( 'percent' === $type && $amount > 100 ) ) {
			return $this->error( 'invalid_amount', 'percent' === $type ? __( 'Enter a percentage between 0 and 100.', 'pointly-booking' ) : __( 'Enter an amount greater than zero.', 'pointly-booking' ), 400, array( 'field' => 'amount' ) );
		}

		$starts = Sanitize::datetime_or_null( $merged['starts_at'] ?? null );
		$ends   = Sanitize::datetime_or_null( $merged['ends_at'] ?? null );
		if ( $ends && Sanitize::date( $merged['ends_at'] ) === (string) $merged['ends_at'] ) {
			// A date-only end means "through the end of that day".
			$ends = substr( $ends, 0, 10 ) . ' 23:59:59';
		}
		if ( $starts && $ends && $ends < $starts ) {
			return $this->error( 'invalid_dates', __( 'The end date must be after the start date.', 'pointly-booking' ), 400, array( 'field' => 'ends_at' ) );
		}

		$max_uses  = ( isset( $merged['max_uses'] ) && '' !== (string) $merged['max_uses'] && null !== $merged['max_uses'] ) ? max( 0, (int) $merged['max_uses'] ) : null;
		$min_total = ( isset( $merged['min_total'] ) && '' !== (string) $merged['min_total'] && null !== $merged['min_total'] ) ? Sanitize::money( $merged['min_total'] ) : null;

		$data = array(
			'code'      => $code,
			'type'      => $type,
			'amount'    => $amount,
			'starts_at' => $starts,
			'ends_at'   => $ends,
			'max_uses'  => $max_uses ? $max_uses : null,
			'min_total' => $min_total,
			'is_active' => Sanitize::bool01( $merged['is_active'] ?? 1 ),
		);

		if ( $id ) {
			PromoCodeRepository::save( $id, $data );
		} else {
			$id = PromoCodeRepository::create( $data );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The promo code could not be saved.', 'pointly-booking' ), 500 );
			}
		}
		return $this->ok( PromoCodeRepository::find( $id ), $existing ? 200 : 201 );
	}

	/**
	 * Deletes a code.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function delete( \WP_REST_Request $r ) {
		PromoCodeRepository::delete( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * Duplicates a code as CODE-COPY, CODE-COPY2, … (inactive, usage reset).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate( \WP_REST_Request $r ) {
		$promo = PromoCodeRepository::find( (int) $r['id'] );
		if ( ! $promo ) {
			return $this->error( 'not_found', __( 'Promo code not found.', 'pointly-booking' ), 404 );
		}
		$base = substr( $promo['code'], 0, 50 ) . '-COPY';
		$code = $base;
		$n    = 2;
		while ( PromoCodeRepository::find_by_code( $code ) && $n < 1000 ) {
			$code = $base . $n;
			++$n;
		}
		$id = PromoCodeRepository::create(
			array(
				'code'      => $code,
				'type'      => $promo['type'],
				'amount'    => $promo['amount'],
				'starts_at' => $promo['starts_at'],
				'ends_at'   => $promo['ends_at'],
				'max_uses'  => $promo['max_uses'],
				'min_total' => $promo['min_total'],
				'is_active' => 0,
			)
		);
		return $this->ok( PromoCodeRepository::find( $id ), 201 );
	}
}
