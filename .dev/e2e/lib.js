/**
 * Shared helpers for the dev browser checks.
 */
const { chromium } = require( 'playwright' );
const path = require( 'path' );
const fs = require( 'fs' );

const BASE = process.env.PBK_BASE || 'http://localhost:8088';
const SHOTS = path.join( __dirname, 'shots' );
fs.mkdirSync( SHOTS, { recursive: true } );

async function launch() {
	return chromium.launch();
}

async function login( context ) {
	const page = await context.newPage();
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
	await page.close();
}

function watchConsole( page, errors ) {
	page.on( 'console', ( msg ) => {
		if ( msg.type() === 'error' ) {
			errors.push( `[console] ${ msg.text() }` );
		}
	} );
	page.on( 'pageerror', ( error ) => errors.push( `[pageerror] ${ error.message }` ) );
}

async function shot( page, name, options = {} ) {
	const file = path.join( SHOTS, `${ name }.png` );
	await page.screenshot( { path: file, fullPage: options.fullPage !== false, ...options } );
	return file;
}

async function noHorizontalScroll( page ) {
	return page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth + 1 );
}

module.exports = { BASE, SHOTS, launch, login, watchConsole, shot, noHorizontalScroll };
