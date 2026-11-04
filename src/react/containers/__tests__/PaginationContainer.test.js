import { describe, expect, it } from 'vitest';
import {
	getPaginationState,
	getLoadMorePage,
	isLoadMore,
} from '../PaginationContainer';

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

describe( 'isLoadMore', () => {
	it( 'is on for load-more while showing the newest entries', () => {
		expect( isLoadMore( 'load-more', 1 ) ).toBe( true );
	} );

	it( 'falls back to page links after jumping to a later page', () => {
		expect( isLoadMore( 'load-more', 3 ) ).toBe( false );
	} );

	it( 'is off for the default page type', () => {
		expect( isLoadMore( 'page', 1 ) ).toBe( false );
		expect( isLoadMore( undefined, 1 ) ).toBe( false );
	} );
} );

describe( 'getLoadMorePage', () => {
	it( 'asks for the page after the ones shown', () => {
		expect( getLoadMorePage( 5, 5 ) ).toBe( 2 );
		expect( getLoadMorePage( 10, 5 ) ).toBe( 3 );
	} );

	it( 'rounds down when new entries were added above', () => {
		expect( getLoadMorePage( 7, 5 ) ).toBe( 2 );
	} );
} );
