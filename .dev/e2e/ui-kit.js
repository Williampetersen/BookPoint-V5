/**
 * Screenshots of the design-system gallery. Run: node .dev/e2e/ui-kit.js
 */
const { BASE, launch, login, watchConsole, shot, noHorizontalScroll } = require( './lib' );

( async () => {
	const browser = await launch();
	const errors = [];
	const results = [];
	for ( const [ width, height ] of [ [ 1440, 900 ], [ 768, 1024 ], [ 375, 812 ] ] ) {
		const context = await browser.newContext( { viewport: { width, height } } );
		await login( context );
		const page = await context.newPage();
		watchConsole( page, errors );
		await page.goto( `${ BASE }/wp-admin/admin.php?page=pointlybooking_dashboard&pbk_ui_kit=1` );
		await page.waitForSelector( '.pbk-kit__title' );
		await page.waitForTimeout( 400 );
		await shot( page, `uikit-${ width }-light` );
		results.push( `${ width }px no horizontal scroll: ${ await noHorizontalScroll( page ) }` );

		await page.getByRole( 'tab', { name: 'Dark' } ).click();
		await page.waitForTimeout( 300 );
		await shot( page, `uikit-${ width }-dark` );
		await page.getByRole( 'tab', { name: 'Light' } ).click();

		await page.getByRole( 'button', { name: 'Open modal' } ).click();
		await page.waitForTimeout( 400 );
		await shot( page, `uikit-${ width }-modal`, { fullPage: false } );
		const focusedInModal = await page.evaluate( () => !! document.activeElement.closest( '.pbk-modal' ) );
		results.push( `${ width }px focus moved into modal: ${ focusedInModal }` );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 350 );
		results.push( `${ width }px modal closed by Escape: ${ ( await page.locator( '.pbk-modal' ).count() ) === 0 }` );

		await page.getByRole( 'button', { name: 'Open drawer' } ).click();
		await page.waitForTimeout( 400 );
		await shot( page, `uikit-${ width }-drawer`, { fullPage: false } );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 350 );

		// Keyboard: calendar arrow navigation.
		const day = page.locator( '.pbk-calendar__day[tabindex="0"]' ).first();
		await day.focus();
		const before = await day.getAttribute( 'data-date' );
		await page.keyboard.press( 'ArrowRight' );
		const after = await page.evaluate( () => document.activeElement.getAttribute( 'data-date' ) );
		results.push( `${ width }px calendar ArrowRight ${ before } → ${ after }` );

		await context.close();
	}
	await browser.close();
	console.log( results.join( '\n' ) );
	console.log( errors.length ? `\nERRORS:\n${ errors.join( '\n' ) }` : '\nNo console errors.' );
} )();
