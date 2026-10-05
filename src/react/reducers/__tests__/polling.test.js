import pollingData from '../../mockData/reducers/polling';
import apiData from '../../mockData/reducers/api';
import { applyUpdate, getNewestEntry } from '../../utils/utils';
import { initialState, polling } from '../polling';
import { getEntriesSuccess, pollingSuccess } from '../../actions/apiActions';

describe( 'polling reducer', () => {
	it( 'should return the initial state', () => {
		expect( polling( undefined, {} ) ).toEqual( initialState );
	} );

	let shouldRenderNewEntries = true;

	const stateAfterGetEntriesSuccess = {
		...initialState,
		newestEntry: getNewestEntry(
			initialState.newestEntry,
			apiData.entries[ 0 ]
		),
		entries: shouldRenderNewEntries ? {} : initialState.newestEntry,
		knownEntryIds: {
			id_2973: true,
			id_2974: true,
			id_2975: true,
			id_2976: true,
			id_2977: true,
		},
	};

	it( 'should handle GET_ENTRIES_SUCCESS', () => {
		expect( polling( initialState, getEntriesSuccess( apiData ) ) ).toEqual(
			stateAfterGetEntriesSuccess
		);
	} );

	shouldRenderNewEntries = false;

	const stateAfterPollingSuccess = {
		...stateAfterGetEntriesSuccess,
		error: false,
		entries: shouldRenderNewEntries
			? {}
			: applyUpdate(
					stateAfterGetEntriesSuccess.entries,
					pollingData.entries.filter(
						( entry ) => entry.type === 'new'
					)
			  ),
		knownEntryIds: {
			...stateAfterGetEntriesSuccess.knownEntryIds,
			id_3250: true,
		},
		newestEntry: getNewestEntry(
			stateAfterGetEntriesSuccess.newestEntry,
			pollingData.entries[ 0 ]
		),
	};

	it( 'should handle POLLING_SUCCESS', () => {
		expect(
			polling(
				stateAfterGetEntriesSuccess,
				pollingSuccess( pollingData, shouldRenderNewEntries )
			)
		).toEqual( stateAfterPollingSuccess );
	} );

	it( 'deduplicates only the pending queue and still advances polling state', () => {
		const knownEntry = { id: 100, type: 'new', timestamp: 2000 };
		const state = {
			...initialState,
			newestEntry: { id: 90, type: 'new', timestamp: 1990 },
			knownEntryIds: { id_100: true },
		};
		const payload = {
			entries: [ knownEntry ],
			pages: 2,
		};

		const result = polling( state, pollingSuccess( payload, false ) );

		expect( result.entries ).toEqual( {} );
		expect( result.newestEntry ).toEqual( knownEntry );
		expect( result.pages ).toBe( 2 );
	} );

	it( 'removes only rendered duplicates queued before page loading completes', () => {
		const state = {
			...initialState,
			entries: {
				id_100: { id: 100, type: 'new', timestamp: 2000 },
				id_200: { id: 200, type: 'new', timestamp: 2001 },
			},
			knownEntryIds: {
				id_100: true,
				id_200: true,
			},
		};
		const page = {
			entries: [ { id: 100, type: 'update', timestamp: 2002 } ],
			page: 1,
			pages: 1,
		};

		const result = polling( state, getEntriesSuccess( page, false ) );

		expect( result.entries ).toEqual( {
			id_200: { id: 200, type: 'new', timestamp: 2001 },
		} );
	} );

	it( 'records locally rendered polling entries when the queue is cleared', () => {
		const newEntry = { id: 300, type: 'new', timestamp: 2003 };
		const result = polling(
			initialState,
			pollingSuccess( { entries: [ newEntry ] }, true )
		);

		expect( result.entries ).toEqual( {} );
		expect( result.knownEntryIds ).toEqual( { id_300: true } );
	} );
} );
