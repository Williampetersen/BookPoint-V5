/**
 * Media library image picker (wp.media), used by services/categories/extras/staff/locations.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Icon } from '../../ui';
import './media-picker.css';

/**
 * @param {Object}   props
 * @param {number}   props.imageId  Attachment ID (0 = none).
 * @param {string}   props.imageUrl Preview URL for the current image.
 * @param {Function} props.onChange ( id, url ) => void.
 * @param {string}   props.title    Media frame title.
 * @param {string}   props.round    Circular preview (for avatars).
 * @return {*} Picker.
 */
export function MediaPicker( { imageId, imageUrl = '', onChange, title, round = false } ) {
	const [ preview, setPreview ] = useState( imageUrl );

	useEffect( () => {
		setPreview( imageUrl );
	}, [ imageUrl ] );

	const pick = () => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		const frame = window.wp.media( {
			title: title || __( 'Select an image', 'pointly-booking' ),
			button: { text: __( 'Use this image', 'pointly-booking' ) },
			multiple: false,
			library: { type: 'image' },
		} );
		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			const url = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;
			setPreview( url );
			onChange( attachment.id, url );
		} );
		frame.open();
	};

	const remove = () => {
		setPreview( '' );
		onChange( 0, '' );
	};

	return (
		<div className="pbk-media-picker">
			<div className={ `pbk-media-picker__preview ${ round ? 'is-round' : '' }` }>
				{ preview ? <img src={ preview } alt="" /> : <Icon name="image" size={ 22 } /> }
			</div>
			<div className="pbk-media-picker__actions">
				<Button type="button" size="sm" variant="secondary" onClick={ pick }>
					{ imageId ? __( 'Change image', 'pointly-booking' ) : __( 'Choose image', 'pointly-booking' ) }
				</Button>
				{ !! imageId && (
					<Button type="button" size="sm" variant="ghost" onClick={ remove }>
						{ __( 'Remove', 'pointly-booking' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
