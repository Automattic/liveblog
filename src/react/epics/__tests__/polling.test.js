import { lastValueFrom, of, throwError } from 'rxjs';
import { toArray } from 'rxjs/operators';
import { TestScheduler } from 'rxjs/testing';

jest.mock( '../../services/api', () => ( {
	polling: jest.fn(),
	getEntries: jest.fn(),
} ) );

import { polling as pollingApi, getEntries } from '../../services/api';
import { startPollingEpic, mergePollingEpic } from '../polling';

import {
	startPolling,
	cancelPolling,
	pollingSuccess,
	pollingFailed,
	mergePolling,
	mergePollingIntoEntries,
	getEntriesSuccess,
	getEntriesFailed,
} from '../../actions/apiActions';
import { scrollToEntry } from '../../actions/userActions';

import pollingData from '../../mockData/reducers/polling';
import apiData from '../../mockData/reducers/api';

afterEach( () => {
	jest.clearAllMocks();
} );

describe( 'startPollingEpic', () => {
	const stateValue = {
		config: { refresh_interval: 1 },
		api: { entries: {} },
		pagination: { page: 1 },
		polling: { entries: {}, newestEntry: { timestamp: 1511882674 } },
	};

	const run = ( marbles ) => {
		const testScheduler = new TestScheduler( ( actual, expected ) => {
			expect( actual ).toEqual( expected );
		} );
		testScheduler.run( marbles );
	};

	// eslint-disable-next-line jest/expect-expect -- assertion happens inside TestScheduler's comparator via expectObservable().toBe()
	it( 'polls on the configured interval and stops after CANCEL_POLLING', () => {
		pollingApi.mockReturnValue( of( { response: pollingData } ) );

		run( ( { hot, expectObservable } ) => {
			// START_POLLING at frame 1, CANCEL_POLLING at frame 2000 (before the
			// second 1000ms tick at 2001), a trailing no-op action extends the
			// run window past 2001 so a stray second tick would still show up.
			const action$ = hot( '-a 1998ms b 999ms c', {
				a: startPolling(),
				b: cancelPolling(),
				c: pollingFailed(),
			} );

			const output$ = startPollingEpic( action$, { value: stateValue } );

			expectObservable( output$ ).toBe( '1001ms a', {
				a: pollingSuccess( pollingData, true ),
			} );
		} );
	} );

	// eslint-disable-next-line jest/expect-expect -- assertion happens inside TestScheduler's comparator via expectObservable().toBe()
	it( 'emits pollingFailed when the request errors', () => {
		pollingApi.mockReturnValue( throwError( () => new Error( 'boom' ) ) );

		run( ( { hot, expectObservable } ) => {
			const action$ = hot( '-a 1998ms b', {
				a: startPolling(),
				b: cancelPolling(),
			} );

			const output$ = startPollingEpic( action$, { value: stateValue } );

			expectObservable( output$ ).toBe( '1001ms a', {
				a: pollingFailed(),
			} );
		} );
	} );
} );

const runEpic = ( epic, action, state ) =>
	lastValueFrom( epic( of( action ), { value: state } ).pipe( toArray() ) );

describe( 'mergePollingEpic', () => {
	const pollingEntry = pollingData.entries[ 0 ];

	it( 'merges polling entries straight in when already on page 1', async () => {
		const state = {
			pagination: { page: 1, pages: 1 },
			polling: {
				entries: { [ `id_${ pollingEntry.id }` ]: pollingEntry },
				pages: 1,
				newestEntry: pollingEntry,
			},
			config: {},
		};

		const emitted = await runEpic(
			mergePollingEpic,
			mergePolling(),
			state
		);

		expect( emitted ).toEqual( [
			mergePollingIntoEntries( [ pollingEntry ], 1 ),
			scrollToEntry( `id_${ pollingEntry.id }` ),
		] );
	} );

	it( 're-fetches page 1 and scrolls to the newest entry when not on page 1', async () => {
		getEntries.mockReturnValue( of( { response: apiData } ) );

		const state = {
			pagination: { page: 2, pages: 3 },
			polling: {
				entries: { [ `id_${ pollingEntry.id }` ]: pollingEntry },
				pages: 1,
				newestEntry: pollingEntry,
			},
			config: {
				endpoint_url: 'https://example.com/wp-json/liveblog/v1/123/',
				cross_domain: false,
			},
		};

		const emitted = await runEpic(
			mergePollingEpic,
			mergePolling(),
			state
		);

		expect( getEntries ).toHaveBeenCalledWith(
			1,
			state.config,
			pollingEntry
		);
		expect( emitted ).toEqual( [
			getEntriesSuccess( apiData, true ),
			scrollToEntry( `id_${ pollingEntry.id }` ),
		] );
	} );

	it( 'emits getEntriesFailed when the re-fetch errors', async () => {
		getEntries.mockReturnValue( throwError( () => new Error( 'boom' ) ) );

		const state = {
			pagination: { page: 2, pages: 3 },
			polling: {
				entries: { [ `id_${ pollingEntry.id }` ]: pollingEntry },
				pages: 1,
				newestEntry: pollingEntry,
			},
			config: {},
		};

		const emitted = await runEpic(
			mergePollingEpic,
			mergePolling(),
			state
		);

		expect( emitted ).toEqual( [ getEntriesFailed() ] );
	} );
} );
