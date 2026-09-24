<?php
/**
 * Demo data generator (Tools).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CategoryRepository;
use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Repositories\Relations;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Creates realistic, business-neutral sample data.
 */
final class DemoData {

	/**
	 * Generates services, staff, customers and bookings.
	 *
	 * @param int $services  Services (max 20).
	 * @param int $agents    Staff members (max 20).
	 * @param int $customers Customers (max 200).
	 * @param int $bookings  Bookings (max 500).
	 * @return array Counts.
	 */
	public static function generate( $services, $agents, $customers, $bookings ) {
		$services  = max( 0, min( 20, (int) $services ) );
		$agents    = max( 0, min( 20, (int) $agents ) );
		$customers = max( 0, min( 200, (int) $customers ) );
		$bookings  = max( 0, min( 500, (int) $bookings ) );

		$service_names = array(
			array( __( 'Initial consultation', 'pointly-booking' ), 30, 0 ),
			array( __( 'Standard appointment', 'pointly-booking' ), 45, 4500 ),
			array( __( 'Extended session', 'pointly-booking' ), 90, 8500 ),
			array( __( 'Quick check-in', 'pointly-booking' ), 15, 1500 ),
			array( __( 'Premium package', 'pointly-booking' ), 120, 14000 ),
			array( __( 'Follow-up visit', 'pointly-booking' ), 30, 3000 ),
		);
		$first = array( 'Alex', 'Sam', 'Jordan', 'Taylor', 'Morgan', 'Riley', 'Casey', 'Jamie', 'Avery', 'Quinn', 'Robin', 'Charlie' );
		$last  = array( 'Nielsen', 'Garcia', 'Okafor', 'Kim', 'Rossi', 'Novak', 'Silva', 'Dubois', 'Khan', 'Larsen', 'Moreau', 'Weber' );

		$category    = CategoryRepository::create(
			array(
				'name'       => __( 'Popular', 'pointly-booking' ),
				'sort_order' => 0,
				'is_active'  => 1,
			)
		);
		$service_ids = array();
		for ( $i = 0; $i < $services; $i++ ) {
			$def           = $service_names[ $i % count( $service_names ) ];
			$service_ids[] = ServiceRepository::create(
				array(
					/* translators: %s: sample service name */
					'name'                  => $i < count( $service_names ) ? $def[0] : sprintf( __( '%s (copy)', 'pointly-booking' ), $def[0] ),
					'description'           => __( 'Sample service created by the demo data tool.', 'pointly-booking' ),
					'duration_minutes'      => $def[1],
					'price_cents'           => $def[2],
					'buffer_before_minutes' => 0,
					'buffer_after_minutes'  => 0,
					'capacity'              => 1,
					'sort_order'            => $i,
				)
			);
			Relations::set( 'service_categories', 'service_id', end( $service_ids ), array( $category ) );
		}

		$agent_ids = array();
		for ( $i = 0; $i < $agents; $i++ ) {
			$agent_ids[] = AgentRepository::create(
				array(
					'first_name' => $first[ $i % count( $first ) ],
					'last_name'  => $last[ ( $i + 3 ) % count( $last ) ],
					'email'      => 'staff' . ( $i + 1 ) . '@example.test',
					'phone'      => '',
					'is_active'  => 1,
				)
			);
		}
		foreach ( $agent_ids as $agent_id ) {
			Relations::set( 'agent_services', 'agent_id', $agent_id, $service_ids );
		}

		$customer_ids = array();
		$now          = Dates::now_mysql();
		for ( $i = 0; $i < $customers; $i++ ) {
			$customer_ids[] = CustomerRepository::insert(
				array(
					'first_name' => $first[ ( $i * 5 ) % count( $first ) ],
					'last_name'  => $last[ ( $i * 7 ) % count( $last ) ],
					'email'      => 'customer' . ( $i + 1 ) . '-' . wp_generate_password( 4, false, false ) . '@example.test',
					'phone'      => '',
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
		}

		$made = 0;
		if ( $service_ids && $customer_ids ) {
			$statuses = array( 'confirmed', 'confirmed', 'pending', 'completed', 'cancelled' );
			for ( $i = 0; $i < $bookings; $i++ ) {
				$service  = ServiceRepository::find( $service_ids[ array_rand( $service_ids ) ] );
				$date     = Dates::add_days( Dates::today(), wp_rand( -10, 20 ) );
				$minute   = 9 * 60 + wp_rand( 0, 14 ) * 30;
				$agent_id = $agent_ids ? $agent_ids[ array_rand( $agent_ids ) ] : null;
				$status   = $statuses[ array_rand( $statuses ) ];
				$ok       = BookingRepository::insert(
					array(
						'service_id'     => (int) $service['id'],
						'customer_id'    => (int) $customer_ids[ array_rand( $customer_ids ) ],
						'agent_id'       => $agent_id,
						'start_datetime' => Dates::datetime( $date, $minute ),
						'end_datetime'   => Dates::datetime( $date, $minute + (int) $service['duration_minutes'] ),
						'status'         => $status,
						'manage_key'     => BookingRepository::new_key(),
						'total_price'    => $service['price_cents'] / 100,
						'currency'       => \PointlyBooking\Support\Money::currency(),
						'payment_method' => 'cash',
						'payment_status' => 'completed' === $status ? 'paid' : 'unpaid',
						'created_at'     => $now,
						'updated_at'     => $now,
					)
				);
				if ( $ok ) {
					++$made;
				}
			}
		}
		Cache::flush_all();

		return array(
			'services_created'  => count( $service_ids ),
			'agents_created'    => count( $agent_ids ),
			'customers_created' => count( $customer_ids ),
			'bookings_created'  => $made,
		);
	}
}
