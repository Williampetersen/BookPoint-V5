/**
 * Two visitors try to book the same time with the same staff member.
 * Run: node .dev/e2e/double-booking.js
 */
const { BASE, launch, watchConsole, shot } = require( './lib' );

async function toReview( page, email ) {
	await page.goto( `${ BASE }/book-inline/` );
	await page.waitForSelector( '.pbk-wizard__title' );
	let chosen = '';
	for ( let guard = 0; guard < 10; guard++ ) {
		const title = ( await page.locator( '.pbk-wizard__title' ).first().textContent() ).trim();
		if ( /review/i.test( title ) ) {
			return chosen;
		}
		if ( /looking for|service|location/i.test( title ) ) {
			await page.locator( '.pbk-choice' ).first().click();
		} else if ( /who would you like/i.test( title ) ) {
			await page.locator( '.pbk-choice' ).nth( 1 ).click();
		} else if ( /date and time/i.test( title ) ) {
			await page.waitForSelector( '.pbk-slot:not(.pbk-slot--skeleton)' );
			const slot = page.locator( '.pbk-slot:not(.pbk-slot--skeleton)' ).last();
			chosen = ( await slot.textContent() ).trim();
			await slot.click();
		} else if ( /your details/i.test( title ) ) {
			await page.fill( 'input[name="pbk_customer_first_name"]', 'Race' );
			await page.fill( 'input[name="pbk_customer_email"]', email );
		}
		await page.locator( '.pbk-wizard__next' ).click();
		await page.waitForTimeout( 400 );
	}
	return chosen;
}

( async () => {
	const browser = await launch();
	const errors = [];
	const a = await ( await browser.newContext( { viewport: { width: 1280, height: 860 } } ) ).newPage();
	const b = await ( await browser.newContext( { viewport: { width: 1280, height: 860 } } ) ).newPage();
	watchConsole( a, errors );
	watchConsole( b, errors );
	const stamp = Date.now();
	const slotA = await toReview( a, `race-a-${ stamp }@example.com` );
	const slotB = await toReview( b, `race-b-${ stamp }@example.com` );
	console.log( `A picked ${ slotA }, B picked ${ slotB }` );

	// Both confirm at (almost) the same moment.
	await Promise.all( [ a.locator( '.pbk-wizard__next' ).click(), b.locator( '.pbk-wizard__next' ).click() ] );
	await a.waitForTimeout( 2500 );
	const aOk = await a.locator( '.pbk-success' ).count();
	const bOk = await b.locator( '.pbk-success' ).count();
	const aNotice = ( await a.locator( '.pbk-wizard__notice' ).count() ) ? await a.locator( '.pbk-wizard__notice' ).textContent() : '';
	const bNotice = ( await b.locator( '.pbk-wizard__notice' ).count() ) ? await b.locator( '.pbk-wizard__notice' ).textContent() : '';
	const bTitle = ( await b.locator( '.pbk-wizard__title, .pbk-success__title' ).first().textContent() ).trim();
	await shot( b, 'double-booking-loser', { fullPage: false } );
	console.log( `A success=${ aOk } notice="${ aNotice.trim() }"` );
	console.log( `B success=${ bOk } notice="${ bNotice.trim() }" screen="${ bTitle }"` );
	console.log( aOk + bOk === 1 ? 'PASS exactly one booking succeeded' : 'FAIL both or neither succeeded' );
	await browser.close();
	console.log( errors.length ? `ERRORS:\n${ [ ...new Set( errors ) ].join( '\n' ) }` : 'No console errors.' );
} )();
