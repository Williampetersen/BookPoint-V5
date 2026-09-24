/**
 * Block editor script for "bookpoint/booking-form".
 * The front end is rendered on the server (same markup as the shortcode).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit';
import './editor.css';

const icon = (
	<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
		<rect x="3.75" y="5.25" width="16.5" height="15" rx="3" stroke="currentColor" strokeWidth="1.5" />
		<path d="M8 3.5v3.5M16 3.5v3.5M3.75 10h16.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
		<path d="m9.25 14.75 1.9 1.75 3.6-3.75" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
	</svg>
);

registerBlockType( metadata.name, {
	...metadata,
	title: __( 'Booking form', 'pointly-booking' ),
	description: __( 'Let visitors book an appointment: a button that opens the booking window, or the form embedded in the page.', 'pointly-booking' ),
	icon,
	edit: Edit,
	save: () => null,
} );
