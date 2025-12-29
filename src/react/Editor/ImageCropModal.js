/**
 * Image crop modal component.
 *
 * @package Liveblog
 */

import React, { useState, useRef, useCallback, useEffect } from 'react';
import PropTypes from 'prop-types';
import { __ } from '@wordpress/i18n';
import ReactCrop from 'react-image-crop';
import 'react-image-crop/dist/ReactCrop.css';
import { getCroppedImage, scaleToNaturalDimensions } from './cropUtils';

/**
 * Modal component for cropping images before upload.
 *
 * @param {Object}   props               Component props.
 * @param {File}     props.imageFile     The image file to crop.
 * @param {Function} props.onCropComplete Called with the cropped File when user confirms.
 * @param {Function} props.onCancel      Called when user cancels the crop.
 * @param {number}   props.aspectRatio   Optional fixed aspect ratio for the crop.
 * @return {JSX.Element|null} The modal component or null if no image.
 */
function ImageCropModal( { imageFile, onCropComplete, onCancel, aspectRatio } ) {
	const [ crop, setCrop ] = useState();
	const [ imageSrc, setImageSrc ] = useState( '' );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const imageRef = useRef( null );
	const modalRef = useRef( null );

	// Load image preview when file changes.
	useEffect( () => {
		if ( ! imageFile ) {
			return;
		}

		const reader = new FileReader();
		reader.onload = () => setImageSrc( reader.result );
		reader.readAsDataURL( imageFile );
	}, [ imageFile ] );

	// Focus the modal when it opens.
	useEffect( () => {
		if ( modalRef.current ) {
			modalRef.current.focus();
		}
	}, [ imageSrc ] );

	// Handle crop confirmation.
	const handleApplyCrop = useCallback( async () => {
		if ( ! imageRef.current ) {
			onCropComplete( imageFile );
			return;
		}

		// If no crop selected or crop is too small, use original image.
		if ( ! crop?.width || ! crop?.height || crop.width < 10 || crop.height < 10 ) {
			onCropComplete( imageFile );
			return;
		}

		setIsProcessing( true );
		try {
			// Scale crop from displayed size to natural image dimensions.
			const pixelCrop = scaleToNaturalDimensions( crop, imageRef.current );

			const croppedFile = await getCroppedImage(
				imageRef.current,
				pixelCrop,
				imageFile.name
			);
			onCropComplete( croppedFile );
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( 'Crop failed:', error );
			// Fall back to original on error.
			onCropComplete( imageFile );
		} finally {
			setIsProcessing( false );
		}
	}, [ crop, imageFile, onCropComplete ] );

	// Handle keyboard events.
	const handleKeyDown = useCallback(
		( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				onCancel();
			} else if ( event.key === 'Enter' && ! isProcessing ) {
				event.preventDefault();
				handleApplyCrop();
			}
		},
		[ onCancel, handleApplyCrop, isProcessing ]
	);

	// Handle overlay click (close on backdrop click).
	const handleOverlayClick = useCallback(
		( event ) => {
			if ( event.target === event.currentTarget ) {
				onCancel();
			}
		},
		[ onCancel ]
	);

	if ( ! imageSrc ) {
		return null;
	}

	return (
		<div
			className="liveblog-crop-modal-overlay"
			onClick={ handleOverlayClick }
			onKeyDown={ handleKeyDown }
			role="presentation"
		>
			<div
				ref={ modalRef }
				className="liveblog-crop-modal"
				role="dialog"
				aria-modal="true"
				aria-labelledby="liveblog-crop-modal-title"
				tabIndex={ -1 }
			>
				<div className="liveblog-crop-modal-header">
					<h2
						id="liveblog-crop-modal-title"
						className="liveblog-crop-modal-title"
					>
						{ __( 'Crop Image', 'liveblog' ) }
					</h2>
					<button
						type="button"
						className="liveblog-crop-modal-close"
						onClick={ onCancel }
						aria-label={ __( 'Close', 'liveblog' ) }
						disabled={ isProcessing }
					>
						<span className="dashicons dashicons-no-alt" />
					</button>
				</div>

				<div className="liveblog-crop-modal-content">
					<ReactCrop
						crop={ crop }
						onChange={ ( c ) => setCrop( c ) }
						aspect={ aspectRatio }
						className="liveblog-crop-react-crop"
					>
						<img
							ref={ imageRef }
							src={ imageSrc }
							alt={ __( 'Crop preview', 'liveblog' ) }
							className="liveblog-crop-modal-image"
						/>
					</ReactCrop>
					<p className="liveblog-crop-modal-hint">
						{ __( 'Drag to select the area you want to keep. Click Apply to use the full image without cropping.', 'liveblog' ) }
					</p>
				</div>

				<div className="liveblog-crop-modal-footer">
					<button
						type="button"
						className="liveblog-btn liveblog-btn-cancel"
						onClick={ onCancel }
						disabled={ isProcessing }
					>
						{ __( 'Cancel', 'liveblog' ) }
					</button>
					<button
						type="button"
						className="liveblog-btn liveblog-btn-primary"
						onClick={ handleApplyCrop }
						disabled={ isProcessing }
					>
						{ isProcessing ? __( 'Processing…', 'liveblog' ) : __( 'Apply', 'liveblog' ) }
					</button>
				</div>
			</div>
		</div>
	);
}

ImageCropModal.propTypes = {
	imageFile: PropTypes.instanceOf( File ).isRequired,
	onCropComplete: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
	aspectRatio: PropTypes.number,
};

ImageCropModal.defaultProps = {
	aspectRatio: undefined,
};

export default ImageCropModal;
