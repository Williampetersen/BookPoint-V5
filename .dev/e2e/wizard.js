/**
 * End-to-end booking through the wizard (modal + inline, desktop + mobile).
 * Run: node .dev/e2e/wizard.js
 */
const { BASE, launch, watchConsole, shot, noHorizontalScroll } = require( './lib' );

async function continueStep( page ) {
	await page.locator( '.pbk-wizard__next' ).click();
	// Wait until the step changed or the submission finished (success screen or notice).
	await page.waitForFunction( () => ! document.querySelector( '.pbk-wizard__next[aria-busy="true"]' ), null, { timeout: 20000 } );
	await page.waitForTimeout( 350 );
}

async function heading( page ) {
	return ( await page.locator( '.pbk-wizard__title' ).first().textContent() ).trim();
}

async function book( page, label, { email, agentIndex = 0 } ) {
	const log = [];
	for ( let guard = 0; guard < 12; guard++ ) {
		if ( await page.locator( '.pbk-success' ).count() ) {
			break;
		}
		const title = await heading( page );
		log.push( title );
		if ( /location/i.test( title ) ) {
			await page.locator( '.pbk-choice' ).first().click();
		} else if ( /category|looking for/i.test( title ) ) {
			await page.locator( '.pbk-choice' ).first().click();
		} else if ( /service/i.test( title ) ) {
			await page.locator( '.pbk-choice' ).first().click();
		} else if ( /extras/i.test( title ) ) {
			// Optional.
		} else if ( /who would you like/i.test( title ) ) {
			await page.locator( '.pbk-choice' ).nth( agentIndex ).click();
		} else if ( /date and time/i.test( title ) ) {
			await page.waitForSelector( '.pbk-slot:not(.pbk-slot--skeleton)', { timeout: 15000 } );
			await shot( page, `${ label }-datetime`, { fullPage: false } );
			await page.locator( '.pbk-slot:not(.pbk-slot--skeleton)' ).first().click();
		} else if ( /your details/i.test( title ) ) {
			// Validation on blur: touch email with a bad value first.
			await page.fill( 'input[name="pbk_customer_email"]', 'not-an-email' );
			await page.locator( 'input[name="pbk_customer_first_name"]' ).click();
			const inlineError = await page.locator( '.pbk-field__error' ).count();
			log.push( `inline email error shown: ${ inlineError > 0 }` );
			await page.fill( 'input[name="pbk_customer_first_name"]', 'Test' );
			await page.fill( 'input[name="pbk_customer_last_name"]', 'Customer' );
			await page.fill( 'input[name="pbk_customer_email"]', email );
			await page.fill( 'input[name="pbk_customer_phone"]', '+45 12 34 56 78' );
			await shot( page, `${ label }-details`, { fullPage: false } );
		} else if ( /pay/i.test( title ) ) {
			await page.locator( '.pbk-choice' ).first().click();
		} else if ( /review/i.test( title ) ) {
			await shot( page, `${ label }-review`, { fullPage: false } );
		}
		await continueStep( page );
		const notice = page.locator( '.pbk-wizard__notice' );
		if ( await notice.count() ) {
			log.push( `notice: ${ ( await notice.textContent() ).trim() }` );
			return { log, notice: ( await notice.textContent() ).trim() };
		}
	}
	await page.waitForSelector( '.pbk-success', { timeout: 15000 } );
	await page.waitForTimeout( 900 );
	await shot( page, `${ label }-success`, { fullPage: false } );
	const ref = ( await page.locator( '.pbk-success__row strong' ).textContent() ).trim();
	return { log, ref };
}

( async () => {
	const browser = await launch();
	const errors = [];
	const results = [];
	const stamp = Date.now();

	for ( const [ width, height, device ] of [ [ 1440, 900, 'desktop' ], [ 390, 844, 'mobile' ] ] ) {
		const context = await browser.newContext( { viewport: { width, height }, hasTouch: device === 'mobile' } );
		const page = await context.newPage();
		watchConsole( page, errors );

		// Modal mode.
		await page.goto( `${ BASE }/book/` );
		await page.waitForSelector( '.pbk-launch:not([disabled])' );
		await shot( page, `wizard-${ device }-page`, { fullPage: false } );
		await page.locator( '.pbk-launch' ).click();
		await page.waitForSelector( '.pbk-wizard__title' );
		await page.waitForTimeout( 500 );
		await shot( page, `wizard-${ device }-open`, { fullPage: false } );
		results.push( `${ device } modal: no horizontal scroll ${ await noHorizontalScroll( page ) }` );
		const outcome = await book( page, `wizard-${ device }-modal`, { email: `e2e-${ device }-${ stamp }@example.com`, agentIndex: 1 } );
		results.push( `${ device } modal steps: ${ outcome.log.join( ' → ' ) }` );
		results.push( `${ device } modal booking: ${ outcome.ref || outcome.notice }` );

		// Confirm-before-close when data was entered.
		await page.goto( `${ BASE }/book/` );
		await page.waitForSelector( '.pbk-launch:not([disabled])' );
		await page.locator( '.pbk-launch' ).click();
		await page.waitForSelector( '.pbk-choice' );
		await page.locator( '.pbk-choice' ).first().click();
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 400 );
		results.push( `${ device } confirm-before-close dialog: ${ ( await page.locator( '[role="alertdialog"]' ).count() ) > 0 }` );
		await shot( page, `wizard-${ device }-confirm-close`, { fullPage: false } );

		// Inline mode.
		await page.goto( `${ BASE }/book-inline/` );
		await page.waitForSelector( '.pbk-wizard__title' );
		await page.waitForTimeout( 500 );
		await shot( page, `wizard-${ device }-inline`, { fullPage: false } );
		results.push( `${ device } inline: no horizontal scroll ${ await noHorizontalScroll( page ) }` );
		const inline = await book( page, `wizard-${ device }-inline`, { email: `e2e-inline-${ device }-${ stamp }@example.com`, agentIndex: 1 } );
		results.push( `${ device } inline booking: ${ inline.ref || inline.notice }` );

		await context.close();
	}

	await browser.close();
	console.log( results.join( '\n' ) );
	console.log( errors.length ? `\nERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : '\nNo console errors.' );
} )();
