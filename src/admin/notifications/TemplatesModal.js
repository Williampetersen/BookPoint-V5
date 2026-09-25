/**
 * Built-in notification templates picker.
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal, Badge, useToast } from '../../ui';
import { client } from '../api';

export default function TemplatesModal( { open, onClose, templates, onInstalled } ) {
	const toast = useToast();

	const install = async ( templateId ) => {
		try {
			await client().post( `admin/notifications/templates/${ templateId }` );
			toast.success( __( 'Notification added.', 'pointly-booking' ) );
			onInstalled();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	return (
		<Modal open={ open } onClose={ onClose } title={ __( 'Add from a template', 'pointly-booking' ) } size="md">
			<div className="pbk-stack">
				{ ( templates || [] ).map( ( t ) => (
					<div key={ t.id } className="pbk-template-card">
						<div className="pbk-row">
							<span className="pbk-template-card__title">{ t.name }</span>
							<span className="pbk-spacer" />
							<Badge tone={ t.recipient === 'customer' ? 'brand' : 'neutral' } size="sm">
								{ t.recipient === 'customer' ? __( 'To customer', 'pointly-booking' ) : __( 'To team', 'pointly-booking' ) }
							</Badge>
						</div>
						<span className="pbk-template-card__meta">{ t.subject }</span>
						<div className="pbk-row">
							<Button size="sm" variant={ t.installed ? 'ghost' : 'primary' } disabled={ t.installed } onClick={ () => install( t.id ) }>
								{ t.installed ? __( 'Already added', 'pointly-booking' ) : __( 'Add', 'pointly-booking' ) }
							</Button>
						</div>
					</div>
				) ) }
			</div>
		</Modal>
	);
}
