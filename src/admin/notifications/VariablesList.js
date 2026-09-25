/**
 * Smart-variables reference: click a token to insert it into the last-focused field.
 */
import { __ } from '@wordpress/i18n';
import { useResource } from '../api';

export function VariablesList( { onInsert } ) {
	const { data: groups } = useResource( 'admin/notifications/smart-variables' );

	if ( ! groups ) {
		return null;
	}

	return (
		<div className="pbk-variables">
			{ groups.map( ( group ) => (
				<div key={ group.label } className="pbk-variables__group">
					<h4 className="pbk-variables__label">{ group.label }</h4>
					<div className="pbk-variables__chips">
						{ group.variables.map( ( v ) => (
							<button key={ v.key } type="button" className="pbk-variables__chip" title={ v.label } onClick={ () => onInsert( `{{${ v.key }}}` ) }>
								{ `{{${ v.key }}}` }
							</button>
						) ) }
					</div>
				</div>
			) ) }
			<p className="pbk-subtle">{ __( 'Click a token to add it to the subject or message (wherever you last clicked).', 'pointly-booking' ) }</p>
		</div>
	);
}
