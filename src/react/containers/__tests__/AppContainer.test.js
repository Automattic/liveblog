import { describe, expect, it } from 'vitest';
import { getVisibleEntries } from '../AppContainer';

const buildState = ( entryOrder ) => ( {
	api: {
		entries: {
			id_1: { id: 1 },
			id_2: { id: 2 },
			id_3: { id: 3 },
		},
	},
	config: { entries_per_page: 2, entry_order: entryOrder },
} );

describe( 'getVisibleEntries', () => {
	it( 'trims to one page when newest first', () => {
		expect(
			getVisibleEntries( buildState( 'desc' ) ).map( ( e ) => e.id )
		).toEqual( [ 1, 2 ] );
	} );

	it( 'keeps every entry when oldest first', () => {
		expect(
			getVisibleEntries( buildState( 'asc' ) ).map( ( e ) => e.id )
		).toEqual( [ 1, 2, 3 ] );
	} );
} );
