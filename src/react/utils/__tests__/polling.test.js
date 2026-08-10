import { filterKnownNewEntries } from '../polling';

describe( 'filterKnownNewEntries', () => {
	const renderedEntries = {
		id_100: { id: 100, type: 'new', timestamp: 2000 },
	};
	const newestEntry = { id: 100, type: 'new', timestamp: 2000 };

	it( 'removes new entries that are already rendered', () => {
		const entries = [
			{ id: 100, type: 'new', timestamp: 2000 },
			{ id: 101, type: 'new', timestamp: 2001 },
		];

		expect(
			filterKnownNewEntries( entries, renderedEntries, newestEntry )
		).toEqual( [ { id: 101, type: 'new', timestamp: 2001 } ] );
	} );

	it( 'removes older new entries that are outside the current rendered page', () => {
		const entries = [ { id: 90, type: 'new', timestamp: 1999 } ];

		expect( filterKnownNewEntries( entries, {}, newestEntry ) ).toEqual( [] );
	} );

	it( 'removes same-second new entries up to the known newest ID', () => {
		const entries = [
			{ id: 99, type: 'new', timestamp: 2000 },
			{ id: 100, type: 'new', timestamp: 2000 },
			{ id: 101, type: 'new', timestamp: 2000 },
		];

		expect( filterKnownNewEntries( entries, {}, newestEntry ) ).toEqual( [
			{ id: 101, type: 'new', timestamp: 2000 },
		] );
	} );

	it( 'uses an initial config baseline to order same-second IDs', () => {
		const configBaseline = { id: 100, timestamp: 2000 };
		const entries = [
			{ id: 99, type: 'new', timestamp: 2000 },
			{ id: 101, type: 'new', timestamp: 2000 },
		];

		expect( filterKnownNewEntries( entries, {}, configBaseline ) ).toEqual( [
			{ id: 101, type: 'new', timestamp: 2000 },
		] );
	} );

	it( 'does not order same-second new entries by an update entry ID', () => {
		const updateBaseline = { id: 50, type: 'update', timestamp: 2000 };
		const entries = [ { id: 40, type: 'new', timestamp: 2000 } ];

		expect( filterKnownNewEntries( entries, {}, updateBaseline ) ).toEqual(
			entries
		);
	} );

	it( 'still removes entries older than an update baseline', () => {
		const updateBaseline = { id: 50, type: 'update', timestamp: 2000 };
		const entries = [ { id: 100, type: 'new', timestamp: 1999 } ];

		expect( filterKnownNewEntries( entries, {}, updateBaseline ) ).toEqual( [] );
	} );

	it( 'keeps updates and deletes for known entry IDs', () => {
		const entries = [
			{ id: 100, type: 'update', timestamp: 2001 },
			{ id: 100, type: 'delete', timestamp: 2002 },
		];

		expect(
			filterKnownNewEntries( entries, renderedEntries, newestEntry )
		).toEqual( entries );
	} );

	it( 'returns all entries when there is no rendered or baseline state', () => {
		const entries = [
			{ id: 100, type: 'new', timestamp: 2000 },
			{ id: 100, type: 'update', timestamp: 2001 },
		];

		expect( filterKnownNewEntries( entries, {}, false ) ).toEqual( entries );
	} );
} );
