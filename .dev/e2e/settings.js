const { BASE, launch, login, watchConsole, shot } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_settings` );
	await page.waitForSelector( '.pbk-design__panel', { timeout: 15000 } );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-settings-general', { fullPage: true } );

	const tabs = [
		[ 'Payments', 'admin-settings-payments' ],
		[ 'Schedule', 'admin-settings-schedule' ],
		[ 'Holidays', 'admin-settings-holidays' ],
		[ 'Promo codes', 'admin-settings-promo' ],
		[ 'Form fields', 'admin-settings-fields' ],
		[ 'Activity log', 'admin-settings-audit' ],
		[ 'Tools', 'admin-settings-tools' ],
	];

	for ( const [ label, shotName ] of tabs ) {
		await page.click( `.pbk-toolbar >> text="${ label }"` );
		await page.waitForTimeout( 600 );
		await shot( page, shotName, { fullPage: true } );
	}

	// Promo code drawer
	await page.click( '.pbk-toolbar >> text="Promo codes"' );
	await page.waitForTimeout( 400 );
	await page.click( 'text="New promo code"' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-settings-promo-drawer' );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// Form field drawer with options (select type)
	await page.click( '.pbk-toolbar >> text="Form fields"' );
	await page.waitForTimeout( 400 );
	await page.click( 'text="New field"' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-settings-field-drawer' );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// Audit log detail
	await page.click( '.pbk-toolbar >> text="Activity log"' );
	await page.waitForTimeout( 500 );
	const auditRow = page.locator( '.pbk-audit-row' ).first();
	if ( await auditRow.count() ) {
		await auditRow.click();
		await page.waitForTimeout( 400 );
		await shot( page, 'admin-settings-audit-detail' );
	}

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
