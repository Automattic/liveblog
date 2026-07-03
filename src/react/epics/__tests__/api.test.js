import { lastValueFrom, of, throwError } from 'rxjs';
import { toArray } from 'rxjs/operators';

jest.mock( '../../services/api', () => ( {
	getEntries: jest.fn(),
	createEntry: jest.fn(),
	updateEntry: jest.fn(),
	deleteEntry: jest.fn(),
} ) );

import {
	getEntries,
	createEntry,
	updateEntry,
	deleteEntry,
} from '../../services/api';

import {
	getEntriesEpic,
	getPaginatedEntriesEpic,
	createEntryEpic,
	updateEntryEpic,
	deleteEntryEpic,
	getEntriesAfterChangeEpic,
} from '../api';

import {
	getEntries as getEntriesAction,
	getEntriesPaginated,
	getEntriesSuccess,
	getEntriesFailed,
	createEntry as createEntryAction,
	createEntrySuccess,
	createEntryFailed,
	updateEntry as updateEntryAction,
	updateEntrySuccess,
	updateEntryFailed,
	deleteEntry as deleteEntryAction,
	deleteEntrySuccess,
	deleteEntryFailed,
	pollingSuccess,
} from '../../actions/apiActions';
import { jumpToEvent } from '../../actions/eventsActions';
import { scrollToEntry } from '../../actions/userActions';
import { getScrollToId } from '../../utils/utils';

import apiData from '../../mockData/reducers/api';

const runEpic = ( epic, action, state ) =>
	lastValueFrom( epic( of( action ), { value: state } ).pipe( toArray() ) );

const baseState = {
	config: {
		endpoint_url: 'https://example.com/wp-json/liveblog/v1/123/',
		cross_domain: false,
	},
	api: { entries: {}, newestEntry: false, nonce: 'nonce-123' },
	pagination: { page: 1 },
	polling: { entries: {} },
};

afterEach( () => {
	jest.clearAllMocks();
} );

describe( 'getEntriesEpic', () => {
	it( 'jumps to the event when the action carries a numeric hash', async () => {
		const emitted = await runEpic(
			getEntriesEpic,
			getEntriesAction( 1, '#2977' ),
			baseState
		);

		expect( emitted ).toEqual( [ jumpToEvent( '2977' ) ] );
		expect( getEntries ).not.toHaveBeenCalled();
	} );

	it( 'fetches entries and emits success when there is no hash', async () => {
		getEntries.mockReturnValue( of( { response: apiData } ) );

		const emitted = await runEpic(
			getEntriesEpic,
			getEntriesAction( 1 ),
			baseState
		);

		expect( getEntries ).toHaveBeenCalledWith(
			1,
			baseState.config,
			baseState.api.newestEntry
		);
		expect( emitted ).toEqual( [ getEntriesSuccess( apiData, true ) ] );
	} );

	it( 'emits getEntriesFailed when the request errors', async () => {
		getEntries.mockReturnValue( throwError( () => new Error( 'boom' ) ) );

		const emitted = await runEpic(
			getEntriesEpic,
			getEntriesAction( 1 ),
			baseState
		);

		expect( emitted ).toEqual( [ getEntriesFailed() ] );
	} );
} );

describe( 'getPaginatedEntriesEpic', () => {
	it( 'emits success then scrolls to the requested entry', async () => {
		getEntries.mockReturnValue( of( { response: apiData } ) );

		const emitted = await runEpic(
			getPaginatedEntriesEpic,
			getEntriesPaginated( 2, 'first' ),
			baseState
		);

		expect( emitted ).toEqual( [
			getEntriesSuccess( apiData, true ),
			scrollToEntry( getScrollToId( apiData.entries, 'first' ) ),
		] );
	} );

	it( 'emits getEntriesFailed when the request errors', async () => {
		getEntries.mockReturnValue( throwError( () => new Error( 'boom' ) ) );

		const emitted = await runEpic(
			getPaginatedEntriesEpic,
			getEntriesPaginated( 2, 'first' ),
			baseState
		);

		expect( emitted ).toEqual( [ getEntriesFailed() ] );
	} );
} );

describe.each( [
	[
		'createEntryEpic',
		createEntryEpic,
		createEntry,
		createEntryAction,
		createEntrySuccess,
		createEntryFailed,
	],
	[
		'updateEntryEpic',
		updateEntryEpic,
		updateEntry,
		updateEntryAction,
		updateEntrySuccess,
		updateEntryFailed,
	],
	[
		'deleteEntryEpic',
		deleteEntryEpic,
		deleteEntry,
		deleteEntryAction,
		deleteEntrySuccess,
		deleteEntryFailed,
	],
] )(
	'%s',
	( name, epic, service, actionCreator, successCreator, failedCreator ) => {
		const payload = { id: '2977', content: 'hi' };

		it( 'calls the service and emits success', async () => {
			service.mockReturnValue( of( { response: payload } ) );

			const emitted = await runEpic(
				epic,
				actionCreator( payload ),
				baseState
			);

			expect( service ).toHaveBeenCalledWith(
				payload,
				baseState.config,
				baseState.api.nonce
			);
			expect( emitted ).toEqual( [ successCreator( payload ) ] );
		} );

		it( 'emits the failed action when the request errors', async () => {
			service.mockReturnValue( throwError( () => new Error( 'boom' ) ) );

			const emitted = await runEpic(
				epic,
				actionCreator( payload ),
				baseState
			);

			expect( emitted ).toEqual( [ failedCreator() ] );
		} );
	}
);

describe( 'getEntriesAfterChangeEpic', () => {
	it.each( [
		[ 'CREATE_ENTRY_SUCCESS', createEntrySuccess ],
		[ 'UPDATE_ENTRY_SUCCESS', updateEntrySuccess ],
		[ 'DELETE_ENTRY_SUCCESS', deleteEntrySuccess ],
	] )(
		'turns %s into a pollingSuccess with renderNewEntries true',
		async ( label, actionCreator ) => {
			const payload = { id: '2977' };

			const emitted = await runEpic(
				getEntriesAfterChangeEpic,
				actionCreator( payload ),
				baseState
			);

			expect( emitted ).toEqual( [ pollingSuccess( payload, true ) ] );
		}
	);
} );
