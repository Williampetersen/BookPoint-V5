const { BASE, launch, login, watchConsole, shot, noHorizontalScroll } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await login( context );
	const page = await context.newPage();
	watchConsole( page, errors );

	await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_calendar` );
	await page.waitForSelector( '.pbk-cal-month', { timeout: 15000 } );
	await page.waitForTimeout( 800 );
	await shot( page, 'admin-calendar-month', { fullPage: true } );
	console.log( 'no horizontal scroll (month):', await noHorizontalScroll( page ) );

	// Switch to week view
	await page.click( '.pbk-tabs >> text="Week"' );
	await page.waitForSelector( '.pbk-cal-timegrid', { timeout: 8000 } );
	await page.waitForTimeout( 500 );
	await shot( page, 'admin-calendar-week', { fullPage: true } );

	// Switch to day view
	await page.click( '.pbk-tabs >> text="Day"' );
	await page.waitForTimeout( 500 );
	await shot( page, 'admin-calendar-day', { fullPage: true } );

	// Switch to list view
	await page.click( '.pbk-tabs >> text="List"' );
	await page.waitForTimeout( 500 );
	await shot( page, 'admin-calendar-list', { fullPage: true } );

	// Click an agenda row to open the booking drawer
	const firstRow = page.locator( '.pbk-cal-agenda__row' ).first();
	if ( await firstRow.count() ) {
		await firstRow.click();
		await page.waitForSelector( '.pbk-booking-detail, .pbk-drawer-form', { timeout: 8000 } );
		await shot( page, 'admin-calendar-drawer-from-list' );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 300 );
	}

	// Back to month, test drag-and-drop reschedule between two day cells
	await page.click( '.pbk-tabs >> text="Month"' );
	await page.waitForSelector( '.pbk-cal-month', { timeout: 8000 } );
	await page.waitForTimeout( 500 );
	const chip = page.locator( '.pbk-cal-chip' ).first();
	if ( await chip.count() ) {
		const chipBox = await chip.boundingBox();
		const days = page.locator( '.pbk-cal-day.is-out, .pbk-cal-day:not(.is-out)' );
		const dayCount = await days.count();
		let targetBox = null;
		for ( let i = 0; i < dayCount; i++ ) {
			const box = await days.nth( i ).boundingBox();
			if ( box && Math.abs( box.y - chipBox.y ) > 100 ) {
				targetBox = box;
				break;
			}
		}
		if ( targetBox ) {
			await page.mouse.move( chipBox.x + chipBox.width / 2, chipBox.y + chipBox.height / 2 );
			await page.mouse.down();
			await page.mouse.move( targetBox.x + targetBox.width / 2, targetBox.y + 20, { steps: 10 } );
			await page.mouse.up();
			await page.waitForTimeout( 1000 );
			await shot( page, 'admin-calendar-after-drag', { fullPage: true } );
		}
	}

	// Add holiday flow
	await page.click( 'text=Add holiday' );
	await page.waitForSelector( 'text=Add a holiday', { timeout: 5000 } );
	await page.fill( '.pbk-modal input[type="text"], .pbk-modal input:not([type])', 'E2E Holiday' ).catch( () => {} );
	await shot( page, 'admin-calendar-holiday-modal' );
	await page.keyboard.press( 'Escape' );

	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
	await browser.close();
} )();
