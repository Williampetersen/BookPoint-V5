const { BASE, launch, login, watchConsole, shot } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_notifications` );
	await page.waitForSelector( '.pbk-reorder-list, .pbk-empty', { timeout: 15000 } );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-notifications-list', { fullPage: true } );

	// Open an existing workflow
	await page.click( '.pbk-workflow-row' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await page.waitForTimeout( 500 );
	await shot( page, 'admin-notifications-edit-setup' );

	await page.click( '.pbk-tabs >> text="Emails"' );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-notifications-edit-emails' );

	// Preview
	await page.click( 'text="Preview"' );
	await page.waitForTimeout( 800 );
	await shot( page, 'admin-notifications-preview' );

	await page.click( '.pbk-tabs >> text="Smart variables"' );
	await page.waitForTimeout( 400 );
	await shot( page, 'admin-notifications-variables' );

	// Insert a variable into subject (click Setup->Emails subject focus first)
	await page.click( '.pbk-tabs >> text="Emails"' );
	await page.waitForTimeout( 300 );
	await page.locator( '.pbk-action-editor input[type="text"]' ).nth( 1 ).click(); // subject field
	await page.click( '.pbk-tabs >> text="Smart variables"' );
	await page.waitForTimeout( 300 );
	await page.click( '.pbk-variables__chip >> nth=0' );
	await page.click( '.pbk-tabs >> text="Emails"' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-notifications-after-insert' );

	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// Templates modal
	await page.click( 'text="Add from template"' );
	await page.waitForSelector( 'text="Add from a template"', { timeout: 5000 } );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-notifications-templates', { fullPage: true } );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
