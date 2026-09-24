<?php
/**
 * Stripe webhook endpoint.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Front;

use PointlyBooking\Rest\Controller;
use PointlyBooking\Services\Payments\Stripe;
use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * POST /webhooks/stripe — the URL is configured in Stripe dashboards and must not change.
 */
final class StripeWebhookController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$this->route(
			'/webhooks/stripe',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				// Authenticity is proven by the Stripe-Signature header, verified in handle().
				'permission_callback' => array( Controller::class, 'public_access' ),
			)
		);
	}

	/**
	 * Verifies and processes an event.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $r ) {
		$secret = (string) Settings::get( 'stripe_webhook_secret', '' );
		if ( '' === $secret ) {
			return $this->error( 'not_configured', 'Webhook secret is not configured.', 400 );
		}
		$payload = (string) $r->get_body();
		if ( ! Stripe::verify_signature( $payload, (string) $r->get_header( 'stripe_signature' ), $secret ) ) {
			return $this->error( 'invalid_signature', 'Invalid signature.', 400 );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return $this->error( 'invalid_payload', 'Invalid payload.', 400 );
		}
		Stripe::handle_event( $event );
		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}
}
