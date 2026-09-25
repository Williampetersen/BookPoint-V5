/**
 * Native HTML5 drag-and-drop row reordering for flat lists of { id }.
 */
import { useRef, useState } from '@wordpress/element';

export function useDragReorder( items, onReorder ) {
	const draggedId = useRef( null );
	const [ hoverId, setHoverId ] = useState( null );

	const rowProps = ( id ) => ( {
		draggable: true,
		onDragStart: () => {
			draggedId.current = id;
		},
		onDragOver: ( e ) => e.preventDefault(),
		onDragEnter: () => setHoverId( id ),
		onDragLeave: () => setHoverId( ( h ) => ( h === id ? null : h ) ),
		onDrop: ( e ) => {
			e.preventDefault();
			setHoverId( null );
			const from = draggedId.current;
			draggedId.current = null;
			if ( from === null || from === id ) {
				return;
			}
			const ids = items.map( ( i ) => i.id );
			const fromIndex = ids.indexOf( from );
			const toIndex = ids.indexOf( id );
			if ( fromIndex === -1 || toIndex === -1 ) {
				return;
			}
			ids.splice( toIndex, 0, ids.splice( fromIndex, 1 )[ 0 ] );
			onReorder( ids );
		},
	} );

	return { rowProps, hoverId };
}
