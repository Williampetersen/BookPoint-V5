/**
 * Build: four bundles in build/<name>/index.js (+ index.css, index.asset.php).
 *
 * - admin   The admin app (one React app for every BookPoint screen; screens are lazy chunks).
 * - front   The booking wizard (shortcode / block).
 * - manage  The manage-booking page and the customer portal.
 * - blocks/booking-form  Block editor script (block.json is copied by wp-scripts).
 *
 * React and WordPress packages come from WordPress itself (dependency extraction), so nothing
 * is loaded from a CDN.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin/index': './src/admin/index.js',
		'front/index': './src/front/index.js',
		'manage/index': './src/manage/index.js',
		'blocks/booking-form/index': './src/blocks/booking-form/index.js',
	},
	output: {
		...defaultConfig.output,
		// Lazy chunks live next to their entry (build/admin/screen-*.js) so their
		// translations can be merged by PointlyBooking\Support\Assets.
		chunkFilename: '[name].js?ver=[contenthash:8]',
	},
	performance: {
		...( defaultConfig.performance || {} ),
		hints: 'warning',
		maxEntrypointSize: 250000,
		maxAssetSize: 250000,
	},
};
