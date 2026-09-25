/**
 * CSV import: a real multipart form post to admin-post.php (full page redirect back).
 */
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Modal, Field, Notice } from '../../ui';
import { useAdminConfig } from '../context';

export default function ImportModal( { open, onClose } ) {
	const config = useAdminConfig();
	const fileRef = useRef( null );
	const [ fileName, setFileName ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );

	return (
		<Modal open={ open } onClose={ onClose } title={ __( 'Import customers', 'pointly-booking' ) } size="sm">
			<form method="post" encType="multipart/form-data" action={ config.adminPostUrl } onSubmit={ () => setSubmitting( true ) }>
				<input type="hidden" name="action" value="pointlybooking_admin_customers_import_csv" />
				<input type="hidden" name="_wpnonce" value={ config.adminNonce } />
				<div className="pbk-stack">
					<Notice tone="info">{ __( 'CSV up to 5 MB, with headers first_name, last_name, email, phone (or a single name column). Existing customers are matched and updated by email.', 'pointly-booking' ) }</Notice>
					<Field label={ __( 'CSV file', 'pointly-booking' ) } required>
						<input
							ref={ fileRef }
							type="file"
							name="file"
							accept=".csv,text/csv"
							className="pbk-input"
							required
							onChange={ ( e ) => setFileName( e.target.files[ 0 ] ? e.target.files[ 0 ].name : '' ) }
						/>
					</Field>
					<div className="pbk-drawer-footer">
						<Button type="button" variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button type="submit" variant="primary" loading={ submitting } disabled={ ! fileName }>
							{ __( 'Import', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			</form>
		</Modal>
	);
}
