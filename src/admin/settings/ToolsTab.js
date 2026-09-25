/**
 * Settings → Tools: system status, maintenance actions, report/settings export-import, run log.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Input, Select, Badge, Skeleton, ErrorState, useToast } from '../../ui';
import { useResource, client } from '../api';
import { useAdminConfig } from '../context';

const RUN_LOG_KEY = 'pointlybooking_tools_run_log';

function loadRunLog() {
	try {
		return JSON.parse( window.localStorage.getItem( RUN_LOG_KEY ) || '[]' );
	} catch ( e ) {
		return [];
	}
}

function saveRunLog( entries ) {
	try {
		window.localStorage.setItem( RUN_LOG_KEY, JSON.stringify( entries.slice( 0, 30 ) ) );
	} catch ( e ) {
		// ignore
	}
}

function downloadBlob( content, filename, mime ) {
	const blob = new Blob( [ content ], { type: mime } );
	const url = URL.createObjectURL( blob );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = filename;
	a.click();
	URL.revokeObjectURL( url );
}

export default function ToolsTab() {
	const toast = useToast();
	const config = useAdminConfig();
	const { data: status, error, reload } = useResource( 'admin/tools/status' );
	const [ busy, setBusy ] = useState( '' );
	const [ testEmail, setTestEmail ] = useState( config.user ? config.user.email : '' );
	const [ webhookEvent, setWebhookEvent ] = useState( 'booking_created' );
	const [ runLog, setRunLog ] = useState( [] );
	const fileRef = useRef( null );

	useEffect( () => {
		setRunLog( loadRunLog() );
	}, [] );

	const logRun = ( label, ok ) => {
		const next = [ { label, ok, at: new Date().toISOString() }, ...runLog ];
		setRunLog( next );
		saveRunLog( next );
	};

	const runAction = async ( action, label, body, successMessage ) => {
		setBusy( action );
		try {
			const result = await client().post( `admin/tools/run/${ action }`, body );
			toast.success( typeof successMessage === 'function' ? successMessage( result ) : successMessage );
			logRun( label, true );
			reload();
		} catch ( e ) {
			toast.error( e.message );
			logRun( label, false );
		} finally {
			setBusy( '' );
		}
	};

	const sendTestEmail = () =>
		runAction( 'email_test', __( 'Send test email', 'pointly-booking' ), { to: testEmail }, ( result ) =>
			sprintf( /* translators: %s: email address */ __( 'Test email sent to %s.', 'pointly-booking' ), result.to )
		);
	const fireWebhook = () =>
		runAction( 'webhook_test', __( 'Fire test webhook', 'pointly-booking' ), { event: webhookEvent }, ( result ) =>
			sprintf( /* translators: %d: HTTP status code */ __( 'Webhook delivered (HTTP %d).', 'pointly-booking' ), result.code )
		);

	const downloadReport = async () => {
		setBusy( 'report' );
		try {
			const report = await client().get( 'admin/tools/report' );
			downloadBlob( JSON.stringify( report, null, 2 ), 'pointly-booking-report.json', 'application/json' );
			logRun( __( 'Download report', 'pointly-booking' ), true );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setBusy( '' );
		}
	};

	const exportSettings = async () => {
		setBusy( 'export' );
		try {
			const data = await client().get( 'admin/tools/export-settings' );
			downloadBlob( JSON.stringify( data, null, 2 ), 'pointly-booking-settings.json', 'application/json' );
			logRun( __( 'Export settings', 'pointly-booking' ), true );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setBusy( '' );
		}
	};

	const importSettings = ( e ) => {
		const file = e.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new FileReader();
		reader.onload = async () => {
			setBusy( 'import' );
			try {
				const json = JSON.parse( String( reader.result ) );
				await client().post( 'admin/tools/import-settings', { data: json } );
				toast.success( __( 'Settings imported.', 'pointly-booking' ) );
				logRun( __( 'Import settings', 'pointly-booking' ), true );
			} catch ( err ) {
				toast.error( err.message || __( 'That file could not be imported.', 'pointly-booking' ) );
				logRun( __( 'Import settings', 'pointly-booking' ), false );
			} finally {
				setBusy( '' );
				if ( fileRef.current ) {
					fileRef.current.value = '';
				}
			}
		};
		reader.readAsText( file );
	};

	if ( error && ! status ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}
	if ( ! status ) {
		return <Skeleton variant="block" height={ 320 } />;
	}

	return (
		<div className="pbk-stack">
			<div className="pbk-design__panel">
				<h3 className="pbk-booking-detail__heading">{ __( 'System status', 'pointly-booking' ) }</h3>
				<div className="pbk-tools-grid">
					<div className="pbk-tools-stat">
						<div className="pbk-tools-stat__label">{ __( 'Plugin version', 'pointly-booking' ) }</div>
						<div className="pbk-tools-stat__value">{ status.plugin_version }</div>
					</div>
					<div className="pbk-tools-stat">
						<div className="pbk-tools-stat__label">{ __( 'Database', 'pointly-booking' ) }</div>
						<div className="pbk-tools-stat__value">
							{ status.tables_ok_count }/{ status.tables_total }{ ' ' }
							<Badge tone={ status.tables_ok_count === status.tables_total ? 'success' : 'danger' } size="sm">
								{ status.tables_ok_count === status.tables_total ? __( 'OK', 'pointly-booking' ) : __( 'Missing tables', 'pointly-booking' ) }
							</Badge>
						</div>
					</div>
					<div className="pbk-tools-stat">
						<div className="pbk-tools-stat__label">{ __( 'WordPress / PHP', 'pointly-booking' ) }</div>
						<div className="pbk-tools-stat__value">{ status.wp_version } / { status.php_version }</div>
					</div>
					<div className="pbk-tools-stat">
						<div className="pbk-tools-stat__label">{ __( 'Email', 'pointly-booking' ) }</div>
						<div className="pbk-tools-stat__value">
							<Badge tone={ status.emails_enabled ? 'success' : 'neutral' } size="sm">{ status.emails_enabled ? __( 'Enabled', 'pointly-booking' ) : __( 'Disabled', 'pointly-booking' ) }</Badge>
						</div>
					</div>
					<div className="pbk-tools-stat">
						<div className="pbk-tools-stat__label">{ __( 'Services / Staff', 'pointly-booking' ) }</div>
						<div className="pbk-tools-stat__value">{ status.counts.services } / { status.counts.agents }</div>
					</div>
					<div className="pbk-tools-stat">
						<div className="pbk-tools-stat__label">{ __( 'Bookings / Customers', 'pointly-booking' ) }</div>
						<div className="pbk-tools-stat__value">{ status.counts.bookings } / { status.counts.customers }</div>
					</div>
				</div>
			</div>

			<div className="pbk-design__panel">
				<h3 className="pbk-booking-detail__heading">{ __( 'Maintenance', 'pointly-booking' ) }</h3>
				<div className="pbk-tools-actions">
					<div className="pbk-tools-action">
						<strong>{ __( 'Sync relations', 'pointly-booking' ) }</strong>
						<span className="pbk-tools-action__desc">{ __( 'Rebuilds legacy columns from the relation tables.', 'pointly-booking' ) }</span>
						<Button size="sm" variant="secondary" loading={ busy === 'sync_relations' } onClick={ () => runAction( 'sync_relations', __( 'Sync relations', 'pointly-booking' ), {}, __( 'Relations synced.', 'pointly-booking' ) ) }>
							{ __( 'Run', 'pointly-booking' ) }
						</Button>
					</div>
					<div className="pbk-tools-action">
						<strong>{ __( 'Generate demo data', 'pointly-booking' ) }</strong>
						<span className="pbk-tools-action__desc">{ __( 'Adds sample services, staff, customers and bookings.', 'pointly-booking' ) }</span>
						<Button size="sm" variant="secondary" loading={ busy === 'generate_demo' } onClick={ () => runAction( 'generate_demo', __( 'Generate demo data', 'pointly-booking' ), {}, __( 'Demo data generated.', 'pointly-booking' ) ) }>
							{ __( 'Run', 'pointly-booking' ) }
						</Button>
					</div>
					<div className="pbk-tools-action">
						<strong>{ __( 'Reset cache', 'pointly-booking' ) }</strong>
						<span className="pbk-tools-action__desc">{ __( 'Clears cached catalog, availability and settings data.', 'pointly-booking' ) }</span>
						<Button size="sm" variant="secondary" loading={ busy === 'reset_cache' } onClick={ () => runAction( 'reset_cache', __( 'Reset cache', 'pointly-booking' ), {}, __( 'Cache cleared.', 'pointly-booking' ) ) }>
							{ __( 'Run', 'pointly-booking' ) }
						</Button>
					</div>
					<div className="pbk-tools-action">
						<strong>{ __( 'Run migrations', 'pointly-booking' ) }</strong>
						<span className="pbk-tools-action__desc">{ __( 'Checks and updates the database schema.', 'pointly-booking' ) }</span>
						<Button size="sm" variant="secondary" loading={ busy === 'run_migrations' } onClick={ () => runAction( 'run_migrations', __( 'Run migrations', 'pointly-booking' ), {}, __( 'Database checked and updated.', 'pointly-booking' ) ) }>
							{ __( 'Run', 'pointly-booking' ) }
						</Button>
					</div>
					<div className="pbk-tools-action">
						<strong>{ __( 'Send test email', 'pointly-booking' ) }</strong>
						<Input type="email" size="sm" value={ testEmail } onChange={ ( e ) => setTestEmail( e.target.value ) } placeholder={ __( 'Recipient', 'pointly-booking' ) } />
						<Button size="sm" variant="secondary" loading={ busy === 'email_test' } disabled={ ! testEmail } onClick={ sendTestEmail }>
							{ __( 'Send', 'pointly-booking' ) }
						</Button>
					</div>
					<div className="pbk-tools-action">
						<strong>{ __( 'Fire test webhook', 'pointly-booking' ) }</strong>
						<Select
							value={ webhookEvent }
							onChange={ ( e ) => setWebhookEvent( e.target.value ) }
							options={ [
								{ value: 'booking_created', label: 'booking_created' },
								{ value: 'booking_status_changed', label: 'booking_status_changed' },
								{ value: 'booking_updated', label: 'booking_updated' },
								{ value: 'booking_cancelled', label: 'booking_cancelled' },
							] }
						/>
						<Button size="sm" variant="secondary" loading={ busy === 'webhook_test' } onClick={ fireWebhook }>
							{ __( 'Fire', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			</div>

			<div className="pbk-design__panel">
				<h3 className="pbk-booking-detail__heading">{ __( 'Reports & settings', 'pointly-booking' ) }</h3>
				<div className="pbk-row">
					<Button variant="secondary" icon="download" loading={ busy === 'report' } onClick={ downloadReport }>
						{ __( 'Download system report', 'pointly-booking' ) }
					</Button>
					<Button variant="secondary" icon="download" loading={ busy === 'export' } onClick={ exportSettings }>
						{ __( 'Export settings', 'pointly-booking' ) }
					</Button>
					<Button variant="secondary" icon="upload" loading={ busy === 'import' } onClick={ () => fileRef.current && fileRef.current.click() }>
						{ __( 'Import settings', 'pointly-booking' ) }
					</Button>
					<input ref={ fileRef } type="file" accept="application/json,.json" style={ { display: 'none' } } onChange={ importSettings } />
				</div>
			</div>

			{ runLog.length > 0 && (
				<div className="pbk-design__panel">
					<h3 className="pbk-booking-detail__heading">{ __( 'Recent runs (this browser)', 'pointly-booking' ) }</h3>
					<div className="pbk-run-log">
						{ runLog.map( ( r, i ) => (
							<div key={ i } className="pbk-run-log__row">
								<span>{ r.label }</span>
								<span>
									<Badge tone={ r.ok ? 'success' : 'danger' } size="sm">{ r.ok ? __( 'OK', 'pointly-booking' ) : __( 'Failed', 'pointly-booking' ) }</Badge>{ ' ' }
									{ new Date( r.at ).toLocaleString() }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }
		</div>
	);
}
