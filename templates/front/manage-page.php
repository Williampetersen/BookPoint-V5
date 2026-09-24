<?php
/**
 * Manage booking page shell. Uses the theme's header and footer.
 *
 * Available: $key (validated manage key or '').
 *
 * @package PointlyBooking
 */

use PointlyBooking\Frontend\ManagePage;

defined( 'ABSPATH' ) || exit;

$pointlybooking_mount = ManagePage::mount( isset( $key ) ? (string) $key : '' );

if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	// Render the template parts before wp_head() so their block styles are enqueued.
	$pointlybooking_header = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' );
	$pointlybooking_footer = do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' );
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pbk-manage-page' ); ?>>
	<?php wp_body_open(); ?>
	<div class="wp-site-blocks">
		<?php
		echo $pointlybooking_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered blocks.
		?>
		<main class="wp-block-group pbk-manage-main">
			<?php
			echo $pointlybooking_mount; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ManagePage::mount().
			?>
		</main>
		<?php
		echo $pointlybooking_footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered blocks.
		?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
	return;
}

get_header();
?>
<main id="primary" class="site-main pbk-manage-main">
	<?php
	echo $pointlybooking_mount; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ManagePage::mount().
	?>
</main>
<?php
get_footer();
