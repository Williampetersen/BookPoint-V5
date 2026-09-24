<?php
/**
 * Printable bookings report (open → Print → Save as PDF).
 *
 * Available variables: $bookings (array of joined booking rows).
 *
 * @package PointlyBooking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template scope, included from a method.
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<title><?php esc_html_e( 'Bookings report', 'pointly-booking' ); ?></title>
<style>
	body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #111827; margin: 32px; }
	h1 { font-size: 20px; margin: 0 0 4px; }
	p.meta { color: #6b7280; font-size: 12px; margin: 0 0 20px; }
	table { width: 100%; border-collapse: collapse; font-size: 12px; }
	th, td { text-align: start; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
	th { background: #f9fafb; font-weight: 600; color: #374151; }
	@media print { body { margin: 0; } .no-print { display: none; } }
</style>
</head>
<body>
	<p class="no-print meta"><?php esc_html_e( 'Tip: use your browser’s Print command (Ctrl+P / Cmd+P) and choose "Save as PDF".', 'pointly-booking' ); ?></p>
	<h1><?php echo esc_html( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) . ' — ' . __( 'Bookings', 'pointly-booking' ) ); ?></h1>
	<p class="meta">
		<?php
		/* translators: 1: generated date/time, 2: number of bookings */
		echo esc_html( sprintf( __( 'Generated %1$s · %2$d bookings', 'pointly-booking' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ), count( $bookings ) ) );
		?>
	</p>
	<table>
		<thead>
			<tr>
				<th>#</th>
				<th><?php esc_html_e( 'When', 'pointly-booking' ); ?></th>
				<th><?php esc_html_e( 'Service', 'pointly-booking' ); ?></th>
				<th><?php esc_html_e( 'Staff', 'pointly-booking' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'pointly-booking' ); ?></th>
				<th><?php esc_html_e( 'Status', 'pointly-booking' ); ?></th>
				<th><?php esc_html_e( 'Total', 'pointly-booking' ); ?></th>
				<th><?php esc_html_e( 'Notes', 'pointly-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $bookings as $b ) : ?>
				<tr>
					<td><?php echo (int) $b['id']; ?></td>
					<td><?php echo esc_html( \PointlyBooking\Support\Dates::format_datetime( $b['start_datetime'] ) ); ?></td>
					<td><?php echo esc_html( (string) $b['service_name'] ); ?></td>
					<td><?php echo esc_html( (string) $b['agent_name'] ); ?></td>
					<td><?php echo esc_html( trim( $b['customer_name'] . ' ' . $b['customer_email'] . ' ' . $b['customer_phone'] ) ); ?></td>
					<td><?php echo esc_html( \PointlyBooking\Services\Notifications\Variables::status_label( (string) $b['status'] ) ); ?></td>
					<td><?php echo esc_html( \PointlyBooking\Support\Money::format( (float) $b['total_price'], (string) $b['currency'] ) ); ?></td>
					<td><?php echo esc_html( (string) $b['notes'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</body>
</html>
