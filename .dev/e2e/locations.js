const { BASE, launch, login, watchConsole, shot } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_locations` );
	await page.waitForSelector( '.pbk-reorder-list, .pbk-empty-state', { timeout: 15000 } );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-locations-list', { fullPage: true } );

	await page.click( 'text="New location"' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-locations-new-drawer' );

	// Open existing location edit (if any exist)
	const editButtons = page.locator( '.pbk-reorder-row .pbk-btn--icon-only' );
	if ( await editButtons.count() ) {
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 300 );
		await editButtons.first().click();
		await page.click( 'text="Edit"' );
		await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
		await page.waitForTimeout( 400 );
		await shot( page, 'admin-locations-edit-drawer' );

		// Toggle custom hours
		const customToggle = page.locator( 'text="Use custom hours"' );
		if ( await customToggle.count() ) {
			await page.click( '.pbk-toggle__switch >> nth=1' ).catch( () => {} );
			await page.waitForTimeout( 300 );
			await shot( page, 'admin-locations-custom-hours' );
		}
	}
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	await page.click( '.pbk-toolbar >> text="Categories"' );
	await page.waitForSelector( '.pbk-reorder-list, .pbk-empty-state', { timeout: 8000 } );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-locations-categories', { fullPage: true } );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
