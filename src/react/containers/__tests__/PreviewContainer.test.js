import { getResponseHtml } from '../PreviewContainer';

describe( 'getResponseHtml', () => {
	it( 'pulls the rendered html out of the ajax response', () => {
		const res = { response: { html: '<p>Preview</p>' } };

		expect( getResponseHtml( res ) ).toBe( '<p>Preview</p>' );
	} );

	it( 'returns undefined when the response has no html', () => {
		const res = { response: {} };

		expect( getResponseHtml( res ) ).toBeUndefined();
	} );
} );
