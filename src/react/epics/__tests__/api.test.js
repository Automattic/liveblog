import { describe, expect, it } from 'vitest';
import { lastValueFrom, of } from 'rxjs';
import { toArray } from 'rxjs/operators';

import { getEntriesAfterChangeEpic } from '../api';
import {
	createEntrySuccess,
	deleteEntrySuccess,
	pollingSuccess,
} from '../../actions/apiActions';
import { api } from '../../reducers/api';

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
