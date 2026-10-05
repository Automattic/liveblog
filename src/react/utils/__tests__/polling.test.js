import {
	filterKnownNewEntries,
	rememberPolledEntries,
	rememberRenderedEntries,
	removeRenderedPendingEntries,
} from '../polling';

describe( 'polling entry deduplication', () => {
	it( 'removes only known new entries', () => {
		const knownEntryIds = { id_100: true };
		const entries = [
			{ id: 100, type: 'new', timestamp: 2000 },
			{ id: 90, type: 'new', timestamp: 1999 },
			{ id: 99, type: 'new', timestamp: 2000 },
			{ id: 100, type: 'update', timestamp: 2001 },
			{ id: 100, type: 'delete', timestamp: 2002 },
		];

		expect( filterKnownNewEntries( entries, knownEntryIds ) ).toEqual( [
			{ id: 90, type: 'new', timestamp: 1999 },
			{ id: 99, type: 'new', timestamp: 2000 },
		] );
	} );

	it( 'deduplicates repeated new IDs within one polling response', () => {
		const entries = [
			{ id: 100, type: 'new', timestamp: 2000 },
			{ id: 100, type: 'new', timestamp: 2000 },
			{ id: 101, type: 'new', timestamp: 2000 },
		];

		expect( filterKnownNewEntries( entries, {} ) ).toEqual( [
			{ id: 100, type: 'new', timestamp: 2000 },
			{ id: 101, type: 'new', timestamp: 2000 },
		] );
	} );

	it( 'remembers updated entries that were rendered by paged loading', () => {
		const knownEntryIds = rememberRenderedEntries( {}, [
			{ id: 100, type: 'update', timestamp: 2001 },
		] );

		expect(
			filterKnownNewEntries(
				[ { id: 100, type: 'new', timestamp: 2000 } ],
				knownEntryIds
			)
		).toEqual( [] );
	} );

	it( 'does not let an unseen polling update suppress a late new entry', () => {
		const knownEntryIds = rememberPolledEntries( {}, [
			{ id: 100, type: 'update', timestamp: 2001 },
		] );
		const delayedNew = [ { id: 100, type: 'new', timestamp: 2000 } ];

		expect( filterKnownNewEntries( delayedNew, knownEntryIds ) ).toEqual(
			delayedNew
		);
	} );

	it( 'does not let an unseen polling delete suppress a late new entry', () => {
		const knownEntryIds = rememberPolledEntries( {}, [
			{ id: 100, type: 'delete', timestamp: 2001 },
		] );
		const delayedNew = [ { id: 100, type: 'new', timestamp: 2000 } ];

		expect( filterKnownNewEntries( delayedNew, knownEntryIds ) ).toEqual(
			delayedNew
		);
	} );

	it( 'remembers conclusive polling IDs without mutating the lookup', () => {
		const knownEntryIds = { id_100: true };
		const updated = rememberPolledEntries( knownEntryIds, [
			{ id: 101, type: 'new' },
			{ id: 102, type: 'update' },
			{ id: 103, type: 'delete' },
		] );

		expect( knownEntryIds ).toEqual( { id_100: true } );
		expect( updated ).toEqual( {
			id_100: true,
			id_101: true,
		} );
	} );

	it( 'reuses the lookup when polling adds no new IDs', () => {
		const knownEntryIds = { id_100: true };

		expect(
			rememberPolledEntries( knownEntryIds, [
				{ id: 100, type: 'new' },
				{ id: 101, type: 'update' },
			] )
		).toBe( knownEntryIds );
	} );

	it( 'removes only pending IDs that a page response actually rendered', () => {
		const pendingEntries = {
			id_100: { id: 100, type: 'new' },
			id_200: { id: 200, type: 'new' },
		};

		expect(
			removeRenderedPendingEntries( pendingEntries, [
				{ id: 100, type: 'update' },
			] )
		).toEqual( {
			id_200: { id: 200, type: 'new' },
		} );
	} );

	it( 'ignores malformed entries without poisoning the lookup', () => {
		const knownEntryIds = {};

		expect(
			rememberRenderedEntries( knownEntryIds, [
				{ type: 'new', timestamp: 2000 },
				{ id: 100, type: 'unexpected', timestamp: 2000 },
			] )
		).toBe( knownEntryIds );
	} );
} );
