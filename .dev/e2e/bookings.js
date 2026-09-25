const { BASE, launch, login, watchConsole, shot, noHorizontalScroll } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	// List view
	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_bookings` );
	await page.waitForSelector( '.pbk-status-pills', { timeout: 15000 } );
	await page.waitForTimeout( 800 );
	await shot( page, 'admin-bookings-list', { fullPage: true } );
	console.log( 'no horizontal scroll (list):', await noHorizontalScroll( page ) );

	// Search + status filter
	await page.fill( '.pbk-toolbar__search input', 'a' );
	await page.waitForTimeout( 600 );
	await shot( page, 'admin-bookings-search', { fullPage: true } );
	await page.fill( '.pbk-toolbar__search input', '' );
	await page.waitForTimeout( 400 );

	// Create flow
	await page.click( 'text=New booking' );
	await page.waitForSelector( '.pbk-drawer-form', { timeout: 8000 } );
	await shot( page, 'admin-bookings-new-drawer' );

	const serviceSelect = page.locator( '.pbk-drawer-form select' ).first();
	await serviceSelect.selectOption( { index: 1 } );
	await page.waitForTimeout( 500 );

	// pick an existing customer via search
	const customerSearch = page.locator( '.pbk-customer-search input' );
	if ( await customerSearch.count() ) {
		await customerSearch.fill( 'legacy0' );
		await page.waitForTimeout( 600 );
		const firstResult = page.locator( '.pbk-customer-results button' ).first();
		if ( await firstResult.count() ) {
			await firstResult.click();
		}
	}

	// open the date popover and pick the first enabled day
	await page.click( '.pbk-date-input' );
	await page.waitForSelector( '.pbk-date-popover', { timeout: 5000 } );
	const dayButton = page.locator( '.pbk-date-popover .pbk-calendar__day:not([disabled])' ).first();
	if ( await dayButton.count() ) {
		await dayButton.click();
		await page.waitForTimeout( 800 );
		const slot = page.locator( '.pbk-slots .pbk-slot' ).first();
		if ( await slot.count() ) {
			await slot.click();
		}
	}
	await shot( page, 'admin-bookings-new-filled' );
	console.log( 'create button disabled?', await page.locator( '.pbk-drawer-footer button:has-text("Create booking")' ).isDisabled() );

	await page.click( 'text=Create booking' );
	await page.waitForTimeout( 1200 );
	await shot( page, 'admin-bookings-after-create', { fullPage: true } );

	// Edit flow: open first row
	const firstRow = page.locator( '.pbk-table tbody tr' ).first();
	if ( await firstRow.count() ) {
		await firstRow.click();
		await page.waitForSelector( '.pbk-booking-detail', { timeout: 8000 } );
		await shot( page, 'admin-bookings-detail' );

		// Reschedule
		const rescheduleBtn = page.locator( 'text=Reschedule' );
		if ( await rescheduleBtn.count() ) {
			await rescheduleBtn.click();
			await page.waitForTimeout(  600 );
			await shot( page, 'admin-bookings-reschedule' );
			const cancelReschedule = page.locator( '.pbk-booking-detail__section >> text=Cancel' );
			if ( await cancelReschedule.count() ) {
				await cancelReschedule.click();
			}
		}

		// Close drawer
		const closeBtn = page.locator( '.pbk-drawer-footer >> text=Close' );
		if ( await closeBtn.count() ) {
			await closeBtn.click();
		}
	}

	await page.waitForTimeout( 500 );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
