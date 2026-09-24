/**
 * Admin app root. Screens are lazy chunks (phase 4); the UI kit gallery is available with WP_DEBUG.
 */
import { lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const UiKit = lazy( () =>
	import( /* webpackChunkName: "admin/ui-kit" */ './dev/UiKit' )
);

export default function App( { config, initialRoute } ) {
	const params = new URLSearchParams( window.location.search );
	if ( config.debug && params.get( 'pbk_ui_kit' ) ) {
		return (
			<Suspense
				fallback={ <p>{ __( 'Loading…', 'pointly-booking' ) }</p> }
			>
				<UiKit config={ config } />
			</Suspense>
		);
	}
	return (
		<div className="pbk-admin-app" data-route={ initialRoute }>
			<p>{ __( 'BookPoint is loading…', 'pointly-booking' ) }</p>
		</div>
	);
}
