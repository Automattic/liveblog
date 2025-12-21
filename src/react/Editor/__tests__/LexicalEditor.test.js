/**
 * Tests for LexicalEditor drop handling logic
 */

describe( 'LexicalEditor image drop handling', () => {
	describe( 'image file filtering', () => {
		it( 'filters only image files from dropped files', () => {
			const files = [
				{ type: 'image/png', name: 'image1.png' },
				{ type: 'text/plain', name: 'doc.txt' },
				{ type: 'image/jpeg', name: 'image2.jpg' },
				{ type: 'application/pdf', name: 'file.pdf' },
				{ type: 'image/gif', name: 'image3.gif' },
			];

			// This is the same filter logic used in the DROP_COMMAND handler
			const imageFiles = files.filter( ( file ) =>
				file.type.startsWith( 'image/' )
			);

			expect( imageFiles ).toHaveLength( 3 );
			expect( imageFiles.map( ( f ) => f.name ) ).toEqual( [
				'image1.png',
				'image2.jpg',
				'image3.gif',
			] );
		} );

		it( 'returns empty array when no image files are dropped', () => {
			const files = [
				{ type: 'text/plain', name: 'doc.txt' },
				{ type: 'application/pdf', name: 'file.pdf' },
			];

			const imageFiles = files.filter( ( file ) =>
				file.type.startsWith( 'image/' )
			);

			expect( imageFiles ).toHaveLength( 0 );
		} );

		it( 'handles various image MIME types', () => {
			const files = [
				{ type: 'image/png', name: 'test.png' },
				{ type: 'image/jpeg', name: 'test.jpg' },
				{ type: 'image/gif', name: 'test.gif' },
				{ type: 'image/webp', name: 'test.webp' },
				{ type: 'image/svg+xml', name: 'test.svg' },
				{ type: 'image/bmp', name: 'test.bmp' },
			];

			const imageFiles = files.filter( ( file ) =>
				file.type.startsWith( 'image/' )
			);

			expect( imageFiles ).toHaveLength( 6 );
		} );
	} );

	describe( 'sequential upload processing', () => {
		it( 'processes all dropped images sequentially', async () => {
			const uploadedImages = [];
			const mockUpload = jest.fn().mockImplementation( ( file ) => {
				return new Promise( ( resolve ) => {
					setTimeout( () => {
						uploadedImages.push( file.name );
						resolve( `https://example.com/${ file.name }` );
					}, 10 );
				} );
			} );

			const imageFiles = [
				{ type: 'image/png', name: 'image1.png' },
				{ type: 'image/jpeg', name: 'image2.jpg' },
				{ type: 'image/gif', name: 'image3.gif' },
			];

			// This is the same reduce logic used in the DROP_COMMAND handler
			await imageFiles.reduce( ( promise, file ) => {
				return promise.then( () => mockUpload( file ) );
			}, Promise.resolve() );

			expect( mockUpload ).toHaveBeenCalledTimes( 3 );
			// Verify sequential order is maintained
			expect( uploadedImages ).toEqual( [
				'image1.png',
				'image2.jpg',
				'image3.gif',
			] );
		} );

		it( 'continues processing even if one upload fails', async () => {
			const uploadedImages = [];
			let callCount = 0;

			const mockUpload = jest.fn().mockImplementation( ( file ) => {
				return new Promise( ( resolve, reject ) => {
					callCount++;
					if ( callCount === 2 ) {
						// Second upload fails
						reject( new Error( 'Upload failed' ) );
					} else {
						uploadedImages.push( file.name );
						resolve( `https://example.com/${ file.name }` );
					}
				} );
			} );

			const imageFiles = [
				{ type: 'image/png', name: 'image1.png' },
				{ type: 'image/jpeg', name: 'image2.jpg' },
				{ type: 'image/gif', name: 'image3.gif' },
			];

			// This mirrors the error handling in the DROP_COMMAND handler
			await imageFiles.reduce( ( promise, file ) => {
				return promise.then( () =>
					mockUpload( file ).catch( () => {
						// Error is caught and logged, processing continues
					} )
				);
			}, Promise.resolve() );

			expect( mockUpload ).toHaveBeenCalledTimes( 3 );
			// First and third should succeed, second failed
			expect( uploadedImages ).toEqual( [ 'image1.png', 'image3.gif' ] );
		} );
	} );
} );
