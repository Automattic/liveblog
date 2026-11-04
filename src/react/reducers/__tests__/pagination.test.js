import { describe, expect, it } from 'vitest';
import { initialState, pagination } from '../pagination';
import { getEntriesSuccess, pollingSuccess } from '../../actions/apiActions';
import { loadConfig } from '../../actions/configActions';
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

	it( 'should keep the current page as the last page when oldest first', () => {
		const state = pagination(
			{ ...initialState, page: 2, pages: 2 },
			loadConfig( { entry_order: 'asc' } )
		);

		expect(
			pagination(
				state,
				pollingSuccess( { entries: [], pages: 3 }, true )
			).pages
		).toEqual( 2 );
	} );
} );
