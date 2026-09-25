/**
 * Captures 4 clean product screenshots for the WordPress.org readme.txt listing.
 */
const { BASE, launch, login } = require( './lib' );
const path = require( 'path' );

const OUT = path.join( __dirname, 'shots' );

async function shotViewport( page, name ) {
	await page.screenshot( { path: path.join( OUT, name ), fullPage: false } );
}

( async () => {
	const browser = await launch();
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();

	// 3. Booking wizard (front end)
	await page.goto( `${ BASE }/book/` );
	await page.waitForSelector( 'text="Book an appointment"', { timeout: 15000 } );
	await page.click( 'text="Book an appointment"' );
	await page.waitForSelector( '.pbk-wizard, [class*="wizard"]', { timeout: 8000 } ).catch( () => {} );
	await page.waitForTimeout( 1000 );
	await shotViewport( page, 'screenshot-3.png' );

	// 4. Notification workflow editor
	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_notifications` );
	await page.waitForSelector( '.pbk-workflow-row', { timeout: 15000 } );
	await page.click( '.pbk-workflow-row' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.click( '.pbk-tabs >> text="Smart variables"' );
	await page.waitForTimeout( 800 );
	await shotViewport( page, 'screenshot-4.png' );

	console.log( 'Screenshots captured.' );
	await browser.close();
} )();
