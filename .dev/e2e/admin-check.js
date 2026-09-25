const { BASE, launch, login, watchConsole, shot } = require( './lib' );
( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );
	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_dashboard` );
	await page.waitForSelector( '.pbk-dashboard', { timeout: 15000 } ).catch( () => {} );
	await page.waitForTimeout( 1200 );
	await shot( page, 'admin-dashboard', { fullPage: true } );
	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
