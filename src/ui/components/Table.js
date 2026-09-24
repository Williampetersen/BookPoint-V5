/**
 * Data table with sorting, selection, skeleton rows and a mobile card layout; Pagination.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Icon } from '../icons';
import { Checkbox } from './Form';
import './feedback.css';
import './table.css';

/**
 * @param {Object}   props
 * @param {Array}    props.columns  [ { key, header, render, sortable, align, width, primary, hideOnMobile, className } ].
 * @param {Array}    props.rows     Rows.
 * @param {string}   props.rowKey   Id property (default "id").
 * @param {Object}   props.sort     { key, order: 'asc'|'desc' }.
 * @param {Function} props.onSort   ( key ) => void.
 * @param {boolean}  props.selectable Show checkboxes.
 * @param {Array}    props.selected Selected ids.
 * @param {Function} props.onSelectionChange ( ids ) => void.
 * @param {*}        props.bulkActions Rendered in the bar when rows are selected.
 * @param {boolean}  props.loading  Skeleton rows.
 * @param {*}        props.empty    Rendered when there are no rows.
 * @param {Function} props.onRowClick ( row ) => void (mouse convenience; keep a real button/link in a cell).
 * @param {string}   props.caption  Accessible caption.
 * @return {*} Table.
 */
export function Table( {
	columns = [],
	rows = [],
	rowKey = 'id',
	sort,
	onSort,
	selectable = false,
	selected = [],
	onSelectionChange,
	bulkActions,
	loading = false,
	skeletonRows = 6,
	empty,
	onRowClick,
	caption,
	className = '',
	rowClassName,
} ) {
	const ids = rows.map( ( row ) => row[ rowKey ] );
	const allSelected =
		ids.length > 0 && ids.every( ( id ) => selected.includes( id ) );
	const someSelected =
		! allSelected && ids.some( ( id ) => selected.includes( id ) );

	const toggleAll = () => {
		if ( ! onSelectionChange ) {
			return;
		}
		onSelectionChange(
			allSelected
				? selected.filter( ( id ) => ! ids.includes( id ) )
				: Array.from( new Set( [ ...selected, ...ids ] ) )
		);
	};
	const toggleOne = ( id ) => {
		if ( onSelectionChange ) {
			onSelectionChange(
				selected.includes( id )
					? selected.filter( ( item ) => item !== id )
					: [ ...selected, id ]
			);
		}
	};

	const colCount = columns.length + ( selectable ? 1 : 0 );

	return (
		<div className={ `pbk-table-wrap ${ className }`.trim() }>
			{ selectable && selected.length > 0 && (
				<div
					className="pbk-table__bulk"
					role="region"
					aria-label={ __( 'Bulk actions', 'pointly-booking' ) }
				>
					<span className="pbk-table__bulk-count">
						{ sprintf(
							/* translators: %d: number of selected rows */
							_n(
								'%d selected',
								'%d selected',
								selected.length,
								'pointly-booking'
							),
							selected.length
						) }
					</span>
					<div className="pbk-table__bulk-actions">
						{ bulkActions }
					</div>
					<button
						type="button"
						className="pbk-table__bulk-clear"
						onClick={ () => onSelectionChange( [] ) }
					>
						{ __( 'Clear selection', 'pointly-booking' ) }
					</button>
				</div>
			) }
			<table className="pbk-table" aria-busy={ loading || undefined }>
				{ caption && (
					<caption className="pbk-sr-only">{ caption }</caption>
				) }
				<thead>
					<tr>
						{ selectable && (
							<th scope="col" className="pbk-table__select">
								<Checkbox
									checked={ allSelected }
									indeterminate={ someSelected }
									onChange={ toggleAll }
									aria-label={ __(
										'Select all',
										'pointly-booking'
									) }
									disabled={ ! rows.length }
								/>
							</th>
						) }
						{ columns.map( ( column ) => {
							const active = sort && sort.key === column.key;
							let ariaSort = column.sortable ? 'none' : undefined;
							if ( active ) {
								ariaSort =
									sort.order === 'asc'
										? 'ascending'
										: 'descending';
							}
							return (
								<th
									key={ column.key }
									scope="col"
									aria-sort={ ariaSort }
									className={ `pbk-table__th is-${
										column.align || 'start'
									} ${
										column.hideOnMobile ? 'hide-mobile' : ''
									}`.trim() }
									style={
										column.width
											? { width: column.width }
											: undefined
									}
								>
									{ column.sortable && onSort ? (
										<button
											type="button"
											className={ `pbk-table__sort ${
												active ? 'is-active' : ''
											}` }
											onClick={ () =>
												onSort( column.key )
											}
										>
											<span>{ column.header }</span>
											<Icon
												name={
													active &&
													sort.order === 'asc'
														? 'chevron-up'
														: 'chevron-down'
												}
												size={ 14 }
												className={
													active ? '' : 'is-idle'
												}
											/>
										</button>
									) : (
										column.header
									) }
								</th>
							);
						} ) }
					</tr>
				</thead>
				<tbody>
					{ loading &&
						Array.from( { length: skeletonRows } ).map(
							( _, i ) => (
								<tr
									key={ `s${ i }` }
									className="pbk-table__skeleton-row"
									aria-hidden="true"
								>
									{ selectable && (
										<td className="pbk-table__select" />
									) }
									{ columns.map( ( column ) => (
										<td
											key={ column.key }
											className={
												column.hideOnMobile
													? 'hide-mobile'
													: ''
											}
										>
											<span
												className="pbk-skeleton pbk-skeleton--text"
												style={ {
													inlineSize: `${
														40 +
														( ( i * 17 +
															column.key.length *
																13 ) %
															50 )
													}%`,
												} }
											/>
										</td>
									) ) }
								</tr>
							)
						) }
					{ ! loading && ! rows.length && (
						<tr className="pbk-table__empty-row">
							<td colSpan={ colCount }>
								{ empty || (
									<p className="pbk-table__empty">
										{ __(
											'Nothing to show yet.',
											'pointly-booking'
										) }
									</p>
								) }
							</td>
						</tr>
					) }
					{ ! loading &&
						rows.map( ( row ) => {
							const id = row[ rowKey ];
							const isSelected = selected.includes( id );
							return (
								<tr
									key={ id }
									className={
										`${ isSelected ? 'is-selected' : '' } ${
											onRowClick ? 'is-clickable' : ''
										} ${
											rowClassName
												? rowClassName( row )
												: ''
										}`.trim() || undefined
									}
									onClick={
										onRowClick
											? ( event ) => {
													if (
														event.target.closest(
															'button, a, input, label, select, textarea'
														)
													) {
														return;
													}
													onRowClick( row );
											  }
											: undefined
									}
								>
									{ selectable && (
										<td className="pbk-table__select">
											<Checkbox
												checked={ isSelected }
												onChange={ () =>
													toggleOne( id )
												}
												aria-label={ __(
													'Select row',
													'pointly-booking'
												) }
											/>
										</td>
									) }
									{ columns.map( ( column ) => (
										<td
											key={ column.key }
											data-label={ cellLabel( column ) }
											className={ `is-${
												column.align || 'start'
											} ${
												column.primary
													? 'is-primary'
													: ''
											} ${
												column.hideOnMobile
													? 'hide-mobile'
													: ''
											} ${
												column.className || ''
											}`.trim() }
										>
											{ column.render
												? column.render( row )
												: row[ column.key ] }
										</td>
									) ) }
								</tr>
							);
						} ) }
				</tbody>
			</table>
		</div>
	);
}

function cellLabel( column ) {
	if ( column.primary || typeof column.header !== 'string' ) {
		return undefined;
	}
	return column.header;
}

function pageList( page, pages ) {
	const out = new Set( [ 1, pages, page, page - 1, page + 1 ] );
	return Array.from( out )
		.filter( ( n ) => n >= 1 && n <= pages )
		.sort( ( a, b ) => a - b );
}

/**
 * @param {Object}   props
 * @param {number}   props.page     Current page (1-based).
 * @param {number}   props.perPage  Page size.
 * @param {number}   props.total    Total rows.
 * @param {Function} props.onChange ( page ) => void.
 * @return {*} Pagination.
 */
export function Pagination( {
	page = 1,
	perPage = 20,
	total = 0,
	onChange,
	className = '',
} ) {
	if ( total <= 0 ) {
		return null;
	}
	const pages = Math.max( 1, Math.ceil( total / perPage ) );
	const from = ( page - 1 ) * perPage + 1;
	const to = Math.min( total, page * perPage );
	const list = pageList( page, pages );

	return (
		<nav
			className={ `pbk-pagination ${ className }`.trim() }
			aria-label={ __( 'Pagination', 'pointly-booking' ) }
		>
			<p className="pbk-pagination__summary">
				{ sprintf(
					/* translators: 1: first row number, 2: last row number, 3: total rows */
					__( '%1$d–%2$d of %3$d', 'pointly-booking' ),
					from,
					to,
					total
				) }
			</p>
			{ pages > 1 && (
				<div className="pbk-pagination__pages">
					<button
						type="button"
						className="pbk-pagination__btn"
						onClick={ () => onChange( page - 1 ) }
						disabled={ page <= 1 }
						aria-label={ __( 'Previous page', 'pointly-booking' ) }
					>
						<Icon
							name="chevron-left"
							size={ 18 }
							className="pbk-flip-rtl"
						/>
					</button>
					{ list.map( ( n, i ) => (
						<span key={ n } className="pbk-pagination__group">
							{ i > 0 && n - list[ i - 1 ] > 1 && (
								<span
									className="pbk-pagination__gap"
									aria-hidden="true"
								>
									…
								</span>
							) }
							<button
								type="button"
								className={ `pbk-pagination__btn ${
									n === page ? 'is-current' : ''
								}` }
								aria-current={ n === page ? 'page' : undefined }
								onClick={ () => onChange( n ) }
							>
								<span className="pbk-sr-only">
									{ __( 'Page', 'pointly-booking' ) }{ ' ' }
								</span>
								{ n }
							</button>
						</span>
					) ) }
					<button
						type="button"
						className="pbk-pagination__btn"
						onClick={ () => onChange( page + 1 ) }
						disabled={ page >= pages }
						aria-label={ __( 'Next page', 'pointly-booking' ) }
					>
						<Icon
							name="chevron-right"
							size={ 18 }
							className="pbk-flip-rtl"
						/>
					</button>
				</div>
			) }
		</nav>
	);
}
