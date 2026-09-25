<?php
/**
 * Dev only: send all WordPress mail to the Mailpit container (http://localhost:8025).
 *
 * @package PointlyBooking
 */

// phpcs:ignoreFile -- Development helper, not shipped.

add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host     = 'mailpit';
		$phpmailer->Port     = 1025;
		$phpmailer->SMTPAuth = false;
	}
);
