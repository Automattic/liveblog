import { describe, expect, it } from 'vitest';
import { initialState, pagination } from '../pagination';
import {
	getEntriesSuccess,
	loadMoreEntriesSuccess,
	pollingSuccess,
} from '../../actions/apiActions';
import apiData from '../../mockData/reducers/api';
import pollingData from '../../mockData/reducers/polling';
import { getPollingPages } from '../../utils/utils';

describe( 'pagination reducer', () => {
	it( 'should return the initial state', () => {
		expect( pagination( undefined, {} ) ).toEqual( initialState );
	} );

	const shouldRenderNewEntries = true;
	const stateAfterGetEntriesSuccess = {
		...initialState,
		pages: Math.max( apiData.pages, 1 ),
		page: apiData.page,
		hasMore: apiData.page < apiData.pages,
	};

	it( 'should handle GET_ENTRIES_SUCCESS', () => {
		expect(
			pagination(
				initialState,
				getEntriesSuccess( apiData, shouldRenderNewEntries )
			)
		).toEqual( stateAfterGetEntriesSuccess );
	} );

	const stateAfterPollingSuccess = {
		...stateAfterGetEntriesSuccess,
		pages: shouldRenderNewEntries
			? getPollingPages(
					stateAfterGetEntriesSuccess.pages,
					pollingData.pages
				)
			: pollingData.pages,
	};

	it( 'should handle POLLING_SUCCESS', () => {
		expect(
			pagination(
				stateAfterGetEntriesSuccess,
				pollingSuccess( pollingData, shouldRenderNewEntries )
			)
		).toEqual( stateAfterPollingSuccess );
	} );

	it( 'should only update hasMore on LOAD_MORE_ENTRIES_SUCCESS', () => {
		const state = { page: 1, pages: 3, total: 15, hasMore: true };

		expect(
			pagination(
				state,
				loadMoreEntriesSuccess( { page: 2, pages: 3, total: 14 } )
			)
		).toEqual( state );

		expect(
			pagination(
				state,
				loadMoreEntriesSuccess( { page: 3, pages: 3, total: 14 } )
			)
		).toEqual( { ...state, hasMore: false } );
	} );
} );
