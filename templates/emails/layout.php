<?php
/**
 * Email layout.
 *
 * Available variables: $body (safe HTML), $subject, $brand (hex), $business, $footer (string[]).
 *
 * @package PointlyBooking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template scope, included from a method.
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $subject ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#111827;">
	<div style="display:none;max-height:0;overflow:hidden;"><?php echo esc_html( $subject ); ?></div>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(17,24,39,0.08);">
					<tr>
						<td style="height:6px;background:<?php echo esc_attr( $brand ); ?>;line-height:6px;font-size:0;">&nbsp;</td>
					</tr>
					<tr>
						<td style="padding:28px 32px 8px;font-size:18px;font-weight:700;color:#111827;"><?php echo esc_html( $business ); ?></td>
					</tr>
					<tr>
						<td style="padding:8px 32px 28px;font-size:15px;line-height:1.6;color:#1f2937;">
							<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already passed through wp_kses() in Mailer::send(). ?>
						</td>
					</tr>
					<?php if ( ! empty( $footer ) ) : ?>
						<tr>
							<td style="padding:16px 32px;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#6b7280;">
								<?php echo esc_html( implode( ' · ', $footer ) ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
