/**
 * Canvas-based image cropping utilities.
 *
 * @package Liveblog
 */

/**
 * Create a cropped image from the source image and crop area.
 *
 * @param {HTMLImageElement} image    The source image element.
 * @param {Object}           crop     The crop area with x, y, width, height in pixels.
 * @param {string}           fileName Original file name for the output.
 * @return {Promise<File>} The cropped image as a File object.
 */
export async function getCroppedImage( image, crop, fileName ) {
	const canvas = document.createElement( 'canvas' );
	const ctx = canvas.getContext( '2d' );

	if ( ! ctx ) {
		throw new Error( 'Could not get canvas context' );
	}

	// Set canvas size to the crop dimensions.
	canvas.width = crop.width;
	canvas.height = crop.height;

	// Draw the cropped portion of the image onto the canvas.
	ctx.drawImage(
		image,
		crop.x,
		crop.y,
		crop.width,
		crop.height,
		0,
		0,
		crop.width,
		crop.height
	);

	// Determine output type from filename.
	const extension = fileName.split( '.' ).pop()?.toLowerCase();
	let mimeType = 'image/jpeg';
	let quality = 0.92;

	if ( extension === 'png' ) {
		mimeType = 'image/png';
		quality = undefined; // PNG doesn't use quality parameter.
	} else if ( extension === 'webp' ) {
		mimeType = 'image/webp';
		quality = 0.92;
	}

	// Convert canvas to Blob, then to File.
	return new Promise( ( resolve, reject ) => {
		canvas.toBlob(
			( blob ) => {
				if ( ! blob ) {
					reject( new Error( 'Canvas is empty' ) );
					return;
				}
				// Create File from Blob to preserve filename.
				const file = new File( [ blob ], fileName, {
					type: mimeType,
					lastModified: Date.now(),
				} );
				resolve( file );
			},
			mimeType,
			quality
		);
	} );
}

/**
 * Convert pixel crop to percentage-based crop.
 *
 * @param {Object}           pixelCrop The crop in pixels { x, y, width, height }.
 * @param {HTMLImageElement} image     The image element.
 * @return {Object} The crop as percentages.
 */
export function pixelCropToPercent( pixelCrop, image ) {
	return {
		x: ( pixelCrop.x / image.naturalWidth ) * 100,
		y: ( pixelCrop.y / image.naturalHeight ) * 100,
		width: ( pixelCrop.width / image.naturalWidth ) * 100,
		height: ( pixelCrop.height / image.naturalHeight ) * 100,
	};
}

/**
 * Convert percentage crop to pixel-based crop.
 *
 * @param {Object}           percentCrop The crop as percentages { x, y, width, height }.
 * @param {HTMLImageElement} image       The image element.
 * @return {Object} The crop in pixels.
 */
export function percentCropToPixel( percentCrop, image ) {
	return {
		x: ( percentCrop.x / 100 ) * image.naturalWidth,
		y: ( percentCrop.y / 100 ) * image.naturalHeight,
		width: ( percentCrop.width / 100 ) * image.naturalWidth,
		height: ( percentCrop.height / 100 ) * image.naturalHeight,
	};
}

/**
 * Scale displayed pixel crop to natural image dimensions.
 *
 * ReactCrop returns pixel values relative to the displayed image size.
 * We need to scale these to the natural image dimensions for canvas drawing.
 *
 * @param {Object}           displayedCrop The crop in displayed pixels { x, y, width, height }.
 * @param {HTMLImageElement} image         The image element.
 * @return {Object} The crop in natural image pixels.
 */
export function scaleToNaturalDimensions( displayedCrop, image ) {
	const scaleX = image.naturalWidth / image.width;
	const scaleY = image.naturalHeight / image.height;

	return {
		x: displayedCrop.x * scaleX,
		y: displayedCrop.y * scaleY,
		width: displayedCrop.width * scaleX,
		height: displayedCrop.height * scaleY,
	};
}
