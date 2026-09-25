const { BASE, launch, login, watchConsole, shot } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_agents` );
	await page.waitForSelector( '.pbk-reorder-list', { timeout: 15000 } );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-staff-list', { fullPage: true } );

	// Edit drawer
	await page.click( '.pbk-reorder-row .pbk-btn--icon-only' );
	await page.click( 'text="Edit"' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-staff-edit-drawer' );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// Working hours modal
	await page.click( '.pbk-reorder-row .pbk-btn--icon-only' );
	await page.click( 'text="Working hours"' );
	await page.waitForSelector( 'text="Working hours"', { timeout: 8000 } );
	await page.waitForTimeout( 500 );
	await shot( page, 'admin-staff-hours-business' );

	await page.click( '.pbk-tabs >> text="Custom hours"' );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-staff-hours-custom' );

	await page.click( 'text="Add time off"' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-staff-hours-timeoff' );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
