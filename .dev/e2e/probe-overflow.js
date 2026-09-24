const { BASE, launch, login } = require( './lib' );
( async () => {
	const url = process.argv[ 2 ] || '/wp-admin/admin.php?page=pointlybooking_dashboard&pbk_ui_kit=1';
	const width = Number( process.argv[ 3 ] || 375 );
	const browser = await launch();
	const context = await browser.newContext( { viewport: { width, height: 800 } } );
	await login( context );
	const page = await context.newPage();
	await page.goto( BASE + url );
	await page.waitForTimeout( 1200 );
	const out = await page.evaluate( ( vw ) => {
		const list = [];
		document.querySelectorAll( 'body *' ).forEach( ( el ) => {
			const r = el.getBoundingClientRect();
			if ( r.right > vw + 1 && r.width > 0 && getComputedStyle( el ).position !== 'fixed' ) {
				list.push( `${ el.tagName.toLowerCase() }.${ String( el.className ).slice( 0, 60 ) } right=${ Math.round( r.right ) } w=${ Math.round( r.width ) }` );
			}
		} );
		return { scrollWidth: document.documentElement.scrollWidth, list: list.slice( 0, 15 ) };
	}, width );
	console.log( JSON.stringify( out, null, 1 ) );
	await browser.close();
} )();
