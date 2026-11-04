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

	it( 'eventsApplyUpdate should add an entry made a key event by an edit, in time order', () => {
		const events = {
			id_3: { id: 3, entry_time: 300, key_event: true },
			id_1: { id: 1, entry_time: 100, key_event: true },
		};
		const result = eventsApplyUpdate( events, [
			{ id: 2, type: 'update', entry_time: 200, key_event: true },
		] );

		expect( Object.keys( result ) ).toEqual( [ 'id_3', 'id_2', 'id_1' ] );
	} );

	it( 'eventsApplyUpdate should remove an entry no longer a key event after an edit', () => {
		const events = {
			id_1: { id: 1, entry_time: 100, key_event: true },
		};
		const result = eventsApplyUpdate( events, [
			{ id: 1, type: 'update', entry_time: 100, key_event: false },
		] );

		expect( result ).toEqual( {} );
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
} );
