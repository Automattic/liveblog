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
	pollingSuccess,
} from '../../actions/apiActions';
import { loadConfig } from '../../actions/configActions';

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

	it( 'should store the entry order on LOAD_CONFIG', () => {
		const state = api(
			initialState,
			loadConfig( {
				entry_order: 'asc',
				latest_entry_id: '1',
				latest_entry_timestamp: '1511136000',
			} )
		);

		expect( state.entryOrder ).toEqual( 'asc' );
	} );

	it( 'should take the newest entry from the end of the page when oldest first', () => {
		const state = {
			...initialState,
			entryOrder: 'asc',
			newestEntry: { id: 1, timestamp: 1 },
		};
		const payload = {
			entries: [
				{ id: 2, type: 'new', timestamp: 2 },
				{ id: 3, type: 'new', timestamp: 3 },
			],
			page: 2,
			pages: 2,
		};

		const nextState = api( state, getEntriesSuccess( payload, true ) );

		expect( nextState.newestEntry ).toEqual( payload.entries[ 1 ] );
	} );

	it( 'should add polled entries at the bottom when oldest first', () => {
		const state = {
			...initialState,
			entryOrder: 'asc',
			entries: { id_1: { id: 1, type: 'new', timestamp: 1 } },
		};
		const payload = {
			entries: [ { id: 2, type: 'new', timestamp: 2 } ],
			pages: 1,
		};

		const nextState = api( state, pollingSuccess( payload, true ) );

		expect( Object.keys( nextState.entries ) ).toEqual( [
			'id_1',
			'id_2',
		] );
		expect( nextState.newestEntry ).toEqual( payload.entries[ 0 ] );
	} );
} );
