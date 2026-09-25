const { BASE, launch, login, watchConsole, shot } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_design_form` );
	await page.waitForSelector( '.pbk-design', { timeout: 15000 } );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-design-steps', { fullPage: true } );

	// Select a different step
	await page.click( '.pbk-design__step-select >> nth=2' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-design-step-selected' );

	// Edit title
	await page.fill( '.pbk-design__panel input[type="text"]', 'Pick your service' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-design-step-title' );

	await page.click( '.pbk-toolbar >> text="Appearance"' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-design-appearance' );

	await page.click( '.pbk-toolbar >> text="Texts"' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-design-texts' );

	await page.click( '.pbk-toolbar >> text="Fields layout"' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-design-fields', { fullPage: true } );

	await page.click( '.pbk-toolbar >> text="Behavior"' );
	await page.waitForTimeout( 300 );
	await shot( page, 'admin-design-behavior' );

	// Save
	await page.click( 'text="Save changes"' );
	await page.waitForTimeout( 1000 );
	await shot( page, 'admin-design-after-save' );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
