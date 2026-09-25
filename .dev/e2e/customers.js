const { BASE, launch, login, watchConsole, shot } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_customers` );
	await page.waitForSelector( '.pbk-table', { timeout: 15000 } );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-customers-list', { fullPage: true } );

	// Open detail/edit drawer for the first customer
	await page.click( '.pbk-table tbody tr' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.waitForTimeout( 500 );
	await shot( page, 'admin-customers-detail-drawer', { fullPage: true } );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// New customer flow
	await page.click( 'text="New customer"' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	const inputs = page.locator( '.pbk-drawer-form input' );
	await inputs.nth( 0 ).fill( 'E2E' );
	await inputs.nth( 1 ).fill( 'Customer' );
	await inputs.nth( 2 ).fill( `e2e-customer-${ Date.now() }@example.test` );
	await shot( page, 'admin-customers-new-drawer' );
	await page.click( 'text="Create customer"' );
	await page.waitForTimeout( 1000 );
	await shot( page, 'admin-customers-after-create', { fullPage: true } );

	// Import modal open (don't submit, just verify it renders)
	await page.click( 'text="Import CSV"' );
	await page.waitForSelector( 'text="Import customers"', { timeout: 5000 } );
	await shot( page, 'admin-customers-import-modal' );
	await page.keyboard.press( 'Escape' );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
