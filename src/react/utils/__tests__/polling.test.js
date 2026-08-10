import { filterRenderedNewEntries } from '../polling';

describe( 'filterRenderedNewEntries', () => {
	const renderedEntries = {
		id_100: { id: 100, type: 'new' },
	};

	it( 'removes new entries that are already rendered', () => {
		const entries = [
			{ id: 100, type: 'new' },
			{ id: 101, type: 'new' },
		];

		expect( filterRenderedNewEntries( entries, renderedEntries ) ).toEqual( [
			{ id: 101, type: 'new' },
		] );
	} );

	it( 'keeps updates and deletes for rendered entry IDs', () => {
		const entries = [
			{ id: 100, type: 'update' },
			{ id: 100, type: 'delete' },
		];

		expect( filterRenderedNewEntries( entries, renderedEntries ) ).toEqual(
			entries
		);
	} );

	it( 'keeps a new entry with a different ID', () => {
		const entries = [ { id: 101, type: 'new', timestamp: 2000 } ];

		expect( filterRenderedNewEntries( entries, renderedEntries ) ).toEqual(
			entries
		);
	} );

	it( 'returns all entries when nothing is rendered yet', () => {
		const entries = [
			{ id: 100, type: 'new' },
			{ id: 100, type: 'update' },
		];

		expect( filterRenderedNewEntries( entries, {} ) ).toEqual( entries );
	} );
} );
