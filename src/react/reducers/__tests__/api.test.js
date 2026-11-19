import { describe, expect, it } from 'vitest';
import {
	applyUpdate,
	pollingApplyUpdate,
	getNewestEntry,
} from '../../utils/utils';

import apiData from '../../mockData/reducers/api';
import pollingData from '../../mockData/reducers/polling';
import { initialState, api } from '../api';
import {
	getEntries,
	getEntriesSuccess,
	getEntriesFailed,
	loadMoreEntries,
	loadMoreEntriesSuccess,
	pollingSuccess,
} from '../../actions/apiActions';

describe( 'api reducer', () => {
	it( 'should return the initial state', () => {
		expect( api( undefined, {} ) ).toEqual( initialState );
	} );

	const stateAfterGetEntries = {
		...initialState,
		error: false,
		loading: true,
	};

	it( 'should handle GET_ENTRIES', () => {
		expect( api( initialState, getEntries() ) ).toEqual(
			stateAfterGetEntries
		);
	} );

	it( 'should handle GET_ENTRIES_FAILED', () => {
		expect( api( stateAfterGetEntries, getEntriesFailed() ) ).toEqual( {
			...stateAfterGetEntries,
			loading: false,
			error: true,
		} );
	} );

	const stateAfterGetEntriesSuccess = {
		...stateAfterGetEntries,
		error: false,
		loading: false,
		entries: applyUpdate( {}, apiData.entries ),
		newestEntry: getNewestEntry(
			stateAfterGetEntries.newestEntry,
			apiData.entries[ 0 ]
		),
	};

	it( 'should handle GET_ENTRIES_SUCCESS', () => {
		expect(
			api( stateAfterGetEntries, getEntriesSuccess( apiData, true ) )
		).toEqual( stateAfterGetEntriesSuccess );
	} );

	const shouldRenderNewEntries = true;

	const stateAfterPollingSuccess = {
		...stateAfterGetEntriesSuccess,
		error: false,
		entries: pollingApplyUpdate(
			stateAfterGetEntriesSuccess.entries,
			pollingData.entries,
			shouldRenderNewEntries
		),
		newestEntry: shouldRenderNewEntries
			? getNewestEntry(
					stateAfterGetEntriesSuccess.newestEntry,
					pollingData.entries[ 0 ]
				)
			: stateAfterGetEntriesSuccess.newestEntry,
	};

	it( 'should handle POLLING_SUCCESS', () => {
		expect(
			api(
				stateAfterGetEntriesSuccess,
				pollingSuccess( pollingData, shouldRenderNewEntries )
			)
		).toEqual( stateAfterPollingSuccess );
	} );

	it( 'should handle LOAD_MORE_ENTRIES without marking the feed as loading', () => {
		expect( api( initialState, loadMoreEntries( 2 ) ) ).toEqual( {
			...initialState,
			loadingMore: true,
		} );
	} );

	it( 'should append older entries on LOAD_MORE_ENTRIES_SUCCESS', () => {
		const state = {
			...initialState,
			loadingMore: true,
			entries: {
				id_3: { id: 3, type: 'new' },
				id_2: { id: 2, type: 'new' },
			},
		};

		const next = api(
			state,
			loadMoreEntriesSuccess( {
				entries: [
					{ id: 2, type: 'new' },
					{ id: 1, type: 'new' },
				],
			} )
		);

		expect( next.loadingMore ).toBe( false );
		expect( Object.keys( next.entries ) ).toEqual( [
			'id_3',
			'id_2',
			'id_1',
		] );
	} );

	it( 'should skip entries newer than the anchor on LOAD_MORE_ENTRIES_SUCCESS', () => {
		const state = {
			...initialState,
			newestEntry: { id: '20', timestamp: 2000 },
			entries: { id_20: { id: '20', type: 'new' } },
		};

		const next = api(
			state,
			loadMoreEntriesSuccess( {
				entries: [
					{ id: '30', type: 'new' },
					{ id: '10', type: 'new' },
				],
			} )
		);

		expect( Object.keys( next.entries ) ).toEqual( [ 'id_20', 'id_10' ] );
	} );

	it( 'should clear loadingMore on GET_ENTRIES_FAILED', () => {
		expect(
			api( { ...initialState, loadingMore: true }, getEntriesFailed() )
				.loadingMore
		).toBe( false );
	} );
} );
