import { describe, expect, it } from 'vitest';
import { applyUpdate, eventsApplyUpdate, pollingApplyUpdate } from '../utils';
import {
	currentEntries,
	newEntries,
	expectedEntries,
	expectedEntriesPolling,
} from '../../mockData/utils/applyUpdateEntries';
import {
	currentEvents,
	newEvents,
	expectedEvents,
} from '../../mockData/utils/eventsApplyUpdateEntries';

describe( 'update utilities', () => {
	it( 'applyUpdate should add, remove and update an entry', () => {
		expect( applyUpdate( currentEntries, newEntries ) ).toEqual(
			expectedEntries
		);
	} );

	it( 'eventsApplyUpdate should add, remove and update an event', () => {
		expect( eventsApplyUpdate( currentEvents, newEvents ) ).toEqual(
			expectedEvents
		);
	} );

	it( 'pollingApplyUpdate should add, remove and update an entry if renderNewEntries is true', () => {
		expect(
			pollingApplyUpdate( currentEntries, newEntries, true )
		).toEqual( expectedEntries );
	} );

	it( 'pollingApplyUpdate only remove and update an entry if renderNewEntries is false', () => {
		expect(
			pollingApplyUpdate( currentEntries, newEntries, false )
		).toEqual( expectedEntriesPolling );
	} );

	it( 'pollingApplyUpdate should add new entries at the top when newest first', () => {
		const current = { id_1: { id: 1, type: 'new' } };
		const added = [ { id: 2, type: 'new' } ];

		expect(
			Object.keys( pollingApplyUpdate( current, added, true ) )
		).toEqual( [ 'id_2', 'id_1' ] );
	} );

	it( 'pollingApplyUpdate should add new entries at the bottom when oldest first', () => {
		const current = { id_1: { id: 1, type: 'new' } };
		const added = [
			{ id: 2, type: 'new' },
			{ id: 3, type: 'new' },
		];

		expect(
			Object.keys( pollingApplyUpdate( current, added, true, true ) )
		).toEqual( [ 'id_1', 'id_2', 'id_3' ] );
	} );
} );
