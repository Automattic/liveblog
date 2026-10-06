import { afterEach, describe, expect, it, vi } from 'vitest';
import { lastValueFrom, of, throwError } from 'rxjs';
import { toArray } from 'rxjs/operators';

vi.mock( '../../services/api', () => ( {
	getEntries: vi.fn(),
	createEntry: vi.fn(),
	updateEntry: vi.fn(),
	deleteEntry: vi.fn(),
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
import { api } from '../../reducers/api';
import { getScrollToId } from '../../utils/utils';

import apiData from '../../mockData/reducers/api';

const runEpic = ( action, state ) =>
	lastValueFrom(
		getEntriesAfterChangeEpic( of( action ), { value: state } ).pipe(
			toArray()
		)
	);

describe( 'getEntriesAfterChangeEpic', () => {
	const own = { id: 30, type: 'new', timestamp: 3000 };

	it( 'renders the change directly when nothing is queued', async () => {
		const payload = { entries: [ own ], nonce: 'abc' };

		const emitted = await runEpic( createEntrySuccess( payload ), {
			polling: { entries: {} },
		} );

		expect( emitted ).toEqual( [ pollingSuccess( payload, true ) ] );
	} );

	it( 'renders queued entries beneath the change instead of dropping them', async () => {
		const queued = {
			id_10: { id: 10, type: 'new', timestamp: 1000 },
			id_20: { id: 20, type: 'new', timestamp: 2000 },
		};

		const [ action ] = await runEpic(
			createEntrySuccess( { entries: [ own ], nonce: 'abc' } ),
			{ polling: { entries: queued } }
		);

		expect( action ).toEqual(
			pollingSuccess(
				{
					entries: [ queued.id_10, queued.id_20, own ],
					nonce: 'abc',
				},
				true
			)
		);

		const rendered = api(
			{ entries: { id_5: { id: 5, type: 'new', timestamp: 500 } } },
			action
		);

		expect( Object.keys( rendered.entries ) ).toEqual( [
			'id_30',
			'id_20',
			'id_10',
			'id_5',
		] );
	} );

	it( 'does not render a queued entry that the change deletes', async () => {
		const [ action ] = await runEpic(
			deleteEntrySuccess( {
				entries: [ { id: 10, type: 'delete', timestamp: 3000 } ],
			} ),
			{
				polling: {
					entries: {
						id_10: { id: 10, type: 'new', timestamp: 1000 },
					},
				},
			}
		);

		expect( api( { entries: {} }, action ).entries ).toEqual( {} );
	} );
} );

const runEpicWith = ( epic, action, state ) =>
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
	vi.clearAllMocks();
} );

describe( 'getEntriesEpic', () => {
	it( 'jumps to the event when the action carries a numeric hash', async () => {
		const emitted = await runEpicWith(
			getEntriesEpic,
			getEntriesAction( 1, '#2977' ),
			baseState
		);

		expect( emitted ).toEqual( [ jumpToEvent( '2977' ) ] );
		expect( getEntries ).not.toHaveBeenCalled();
	} );

	it( 'fetches entries and emits success when there is no hash', async () => {
		getEntries.mockReturnValue( of( { response: apiData } ) );

		const emitted = await runEpicWith(
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

		const emitted = await runEpicWith(
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

		const emitted = await runEpicWith(
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

		const emitted = await runEpicWith(
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

			const emitted = await runEpicWith(
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

			const emitted = await runEpicWith(
				epic,
				actionCreator( payload ),
				baseState
			);

			expect( emitted ).toEqual( [ failedCreator() ] );
		} );
	}
);
