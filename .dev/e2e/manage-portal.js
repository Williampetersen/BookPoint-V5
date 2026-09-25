/**
 * Emails, manage page (reschedule + cancel) and customer portal sign-in.
 * Needs Mailpit (docker compose service "mailpit"). Run: node .dev/e2e/manage-portal.js
 */
const { BASE, launch, watchConsole, shot, noHorizontalScroll } = require( './lib' );

const MAIL = 'http://localhost:8025/api/v1';
const REST = `${ BASE }/wp-json/pointly-booking/v1`;

async function json( url, options ) {
	const response = await fetch( url, options );
	return response.json();
}

async function mailTo( address, since ) {
	for ( let i = 0; i < 20; i++ ) {
		const list = await json( `${ MAIL }/search?query=${ encodeURIComponent( `to:${ address }` ) }` );
		const messages = ( list.messages || [] ).filter( ( m ) => new Date( m.Created ).getTime() >= since );
		if ( messages.length ) {
			return messages;
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
	}
	return [];
}

( async () => {
	const results = [];
	const errors = [];
	const stamp = Date.now();
	const email = `manage-${ stamp }@example.com`;

	// 1. Book through the REST API (same route the wizard uses).
	const bootstrap = ( await json( `${ REST }/wizard/bootstrap` ) ).data;
	const service = bootstrap.services[ 0 ];
	const month = ( await json( `${ REST }/wizard/availability/month?service_id=${ service.id }` ) ).data;
	const day = ( await json( `${ REST }/wizard/availability/day?service_id=${ service.id }&date=${ month.first_available }` ) ).data;
	const created = await json( `${ REST }/wizard/bookings`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( {
			service_id: service.id,
			date: month.first_available,
			start: day.slots[ day.slots.length - 1 ].start,
			fields: { customer: { first_name: 'Mia', last_name: 'Manage', email } },
		} ),
	} );
	const booking = created.data.booking;
	results.push( `booking #${ booking.id } created (${ booking.status })` );

	// 2. Emails fired on the wizard path (B-050).
	const mails = await mailTo( email, stamp - 5000 );
	results.push( `customer emails received: ${ mails.length } → ${ mails.map( ( m ) => m.Subject ).join( ' | ' ) }` );
	const admin = await json( `${ MAIL }/search?query=${ encodeURIComponent( `subject:"#${ booking.id }"` ) }` );
	results.push( `emails mentioning #${ booking.id }: ${ ( admin.messages || [] ).length }` );

	const browser = await launch();
	for ( const [ width, height, device ] of [ [ 1280, 860, 'desktop' ], [ 390, 844, 'mobile' ] ] ) {
		const page = await ( await browser.newContext( { viewport: { width, height } } ) ).newPage();
		watchConsole( page, errors );
		if ( device === 'desktop' ) {
			// 3. Manage page: reschedule, then cancel.
			await page.goto( booking.manage_url );
			await page.waitForSelector( '.pbk-mb__card' );
			await shot( page, `manage-${ device }`, { fullPage: false } );
			await page.getByRole( 'button', { name: 'Reschedule' } ).click();
			await page.waitForSelector( '.pbk-calendar__day:not([aria-disabled="true"])' );
			await page.locator( '.pbk-calendar__day:not([aria-disabled="true"])' ).last().click();
			await page.waitForSelector( '.pbk-slot:not(.pbk-slot--skeleton)' );
			await page.locator( '.pbk-slot:not(.pbk-slot--skeleton)' ).first().click();
			await shot( page, `manage-${ device }-reschedule`, { fullPage: false } );
			await page.locator( '.pbk-mb__actions .pbk-btn--primary' ).click();
			await page.waitForSelector( '.pbk-toast' );
			const newKey = new URL( page.url() ).searchParams.get( 'key' );
			results.push( `reschedule toast: ${ ( await page.locator( '.pbk-toast' ).textContent() ).trim() }` );
			results.push( `manage key rotated in URL: ${ newKey !== booking.key }` );
			await page.getByRole( 'button', { name: 'Cancel booking' } ).click();
			await page.waitForSelector( '[role="alertdialog"]' );
			await page.locator( '[role="alertdialog"] .pbk-btn--danger' ).click();
			await page.waitForTimeout( 1500 );
			results.push( `status after cancel: ${ ( await page.locator( '.pbk-mb__card .pbk-badge' ).first().textContent() ).trim() }` );
			await shot( page, `manage-${ device }-cancelled`, { fullPage: false } );
		}

		// 4. Portal: one-time code by email.
		const since = Date.now();
		await page.goto( `${ BASE }/my-bookings/` );
		await page.waitForSelector( '.pbk-portal' );
		await page.fill( '.pbk-portal input[type="email"]', email );
		await page.getByRole( 'button', { name: 'Send me a code' } ).click();
		await page.waitForSelector( '.pbk-portal__code' );
		const codeMails = await mailTo( email, since - 1000 );
		const message = codeMails.length ? await json( `${ MAIL }/message/${ codeMails[ 0 ].ID }` ) : null;
		const code = message ? ( ( message.Text || '' ).match( /\b(\d{6})\b/ ) || [] )[ 1 ] : '';
		results.push( `${ device } portal code email: ${ code ? 'received' : 'MISSING' }` );
		await page.fill( '.pbk-portal__code', code || '000000' );
		await page.getByRole( 'button', { name: 'Sign in' } ).click();
		await page.waitForSelector( '.pbk-portal__header', { timeout: 10000 } );
		await page.waitForTimeout( 800 );
		await page.getByRole( 'tab', { name: /Past/ } ).click();
		await page.waitForTimeout( 300 );
		results.push( `${ device } portal past bookings listed: ${ await page.locator( '.pbk-portal__item' ).count() }` );
		results.push( `${ device } portal no horizontal scroll: ${ await noHorizontalScroll( page ) }` );
		await shot( page, `portal-${ device }`, { fullPage: false } );
	}
	await browser.close();
	console.log( results.join( '\n' ) );
	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
} )();
