const { BASE, launch, login, watchConsole, shot } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_services` );
	await page.waitForSelector( '.pbk-reorder-list', { timeout: 15000 } );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-services-list', { fullPage: true } );

	// Open edit drawer for the first service
	await page.click( '.pbk-reorder-row .pbk-btn--icon-only' );
	await page.click( 'text="Edit"' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-services-edit-drawer' );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// New service flow
	await page.click( 'text="New service"' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.fill( '#pbk-field-1, .pbk-drawer-form input[type="text"]', 'E2E Test Service' ).catch( async () => {
		const nameInput = page.locator( '.pbk-drawer-form input' ).first();
		await nameInput.fill( 'E2E Test Service' );
	} );
	await shot( page, 'admin-services-new-drawer' );
	await page.click( 'text="Create service"' );
	await page.waitForTimeout( 1000 );
	await shot( page, 'admin-services-after-create', { fullPage: true } );

	// Categories tab
	await page.click( '.pbk-toolbar >> text="Categories"' );
	await page.waitForSelector( '.pbk-reorder-list, .pbk-empty-state', { timeout: 8000 } );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-services-categories', { fullPage: true } );

	// Extras tab
	await page.click( '.pbk-toolbar >> text="Extras"' );
	await page.waitForSelector( '.pbk-reorder-list, .pbk-empty-state', { timeout: 8000 } );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-services-extras', { fullPage: true } );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
