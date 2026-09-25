/**
 * Page header: title, description and action buttons. Screens render their own.
 */
export function PageHeader( { title, description, actions, className = '' } ) {
	return (
		<div className={ `pbk-page-header ${ className }`.trim() }>
			<div className="pbk-page-header__text">
				<h1 className="pbk-page-header__title">{ title }</h1>
				{ description && <p className="pbk-page-header__description">{ description }</p> }
			</div>
			{ actions && <div className="pbk-page-header__actions">{ actions }</div> }
		</div>
	);
}
