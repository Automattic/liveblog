import { getPaginationState } from '../PaginationContainer';

describe( 'getPaginationState', () => {
	it( 'flags the first page', () => {
		expect( getPaginationState( 1, 5 ) ).toEqual( {
			isFirstPage: true,
			isLastPage: false,
		} );
	} );

	it( 'flags the last page', () => {
		expect( getPaginationState( 5, 5 ) ).toEqual( {
			isFirstPage: false,
			isLastPage: true,
		} );
	} );

	it( 'flags a page that is both first and last', () => {
		expect( getPaginationState( 1, 1 ) ).toEqual( {
			isFirstPage: true,
			isLastPage: true,
		} );
	} );

	it( 'flags a middle page as neither first nor last', () => {
		expect( getPaginationState( 3, 5 ) ).toEqual( {
			isFirstPage: false,
			isLastPage: false,
		} );
	} );
} );
