/**
 * Manage-booking page and customer portal entry.
 */
import { createRoot } from '@wordpress/element';
import { ThemeRoot, ConfirmProvider, ToastProvider } from '../ui';
import ManageApp from './ManageApp';
import PortalApp from './PortalApp';
import '../front/front.css';
import './manage.css';

function Root( { config, kind, bookingKey } ) {
	const appearance = config.appearance || {};
	const variant = [
		`pbk-radius--${ appearance.radius || 'rounded' }`,
		appearance.font === 'inherit' ? 'pbk-font--inherit' : '',
	]
		.filter( Boolean )
		.join( ' ' );
	return (
		<ThemeRoot
			brand={ config.primary }
			mode={ appearance.dark || 'light' }
			className={ `pbk-mb-root ${ variant }` }
			portalClassName={ variant }
		>
			<ToastProvider>
				<ConfirmProvider>
					{ kind === 'portal' ? (
						<PortalApp config={ config } />
					) : (
						<ManageApp
							config={ config }
							bookingKey={ bookingKey }
						/>
					) }
				</ConfirmProvider>
			</ToastProvider>
		</ThemeRoot>
	);
}

function boot() {
	const config = window.pointlybooking_MANAGE || {};
	document
		.querySelectorAll(
			'[data-pbk-widget="manage"], [data-pbk-widget="portal"]'
		)
		.forEach( ( node ) => {
			if ( node.dataset.pbkMounted ) {
				return;
			}
			node.dataset.pbkMounted = '1';
			node.innerHTML = '';
			createRoot( node ).render(
				<Root
					config={ config }
					kind={ node.dataset.pbkWidget }
					bookingKey={ node.dataset.pbkKey || '' }
				/>
			);
		} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
