const entryKey = ( entry ) => `id_${ entry.id }`;

/**
 * Return polling `new` entries whose logical IDs are not already known.
 *
 * Deduplication is deliberately based on entry identity, not timestamp
 * ordering: bucketed polling URLs can re-deliver entries the client has.
 *
 * @param {Array}  entries       Polling response entries.
 * @param {Object} knownEntryIds Logical entry IDs already known to the client.
 * @return {Array} New entries that still need to be queued.
 */
export const filterKnownNewEntries = ( entries, knownEntryIds ) =>
	entries.filter(
		( entry ) =>
			entry.type === 'new' && ! knownEntryIds[ entryKey( entry ) ]
	);

const rememberEntryIds = ( knownEntryIds, entries, types ) => ( {
	...knownEntryIds,
	...Object.fromEntries(
		entries
			.filter( ( entry ) => types.includes( entry.type ) )
			.map( ( entry ) => [ entryKey( entry ), true ] )
	),
} );

/**
 * Remember IDs for entries loaded as a rendered page.
 *
 * Paged responses are flattened server-side, so any logical ID present there
 * is known to the client even when the returned representation is an update of
 * an older entry.
 *
 * @param {Object} knownEntryIds Logical entry IDs already known to the client.
 * @param {Array}  entries       Entries rendered in the current page.
 * @return {Object} Updated known-entry ID lookup.
 */
export const rememberRenderedEntries = ( knownEntryIds, entries ) =>
	rememberEntryIds( knownEntryIds, entries, [ 'new', 'update' ] );

/**
 * Remember IDs that are conclusive when received through polling.
 *
 * Only a `new` entry proves that its logical ID has actually been delivered by
 * polling. An unseen `update` or `delete` is not used as deduplication evidence,
 * because the original `new` entry may still arrive later.
 *
 * @param {Object} knownEntryIds Logical entry IDs already known to the client.
 * @param {Array}  entries       Unmodified polling response entries.
 * @return {Object} Updated known-entry ID lookup.
 */
export const rememberPolledEntries = ( knownEntryIds, entries ) =>
	rememberEntryIds( knownEntryIds, entries, [ 'new' ] );

/**
 * Remove pending entries that have since been delivered in a rendered page.
 *
 * This closes the race where polling completes before the initial or paginated
 * GET_ENTRIES request. Only IDs present in that rendered response are removed;
 * unrelated genuine pending entries remain queued.
 *
 * @param {Object} pendingEntries  Pending `new` entries keyed by logical ID.
 * @param {Array}  renderedEntries Entries returned by the rendered page load.
 * @return {Object} Updated pending-entry lookup.
 */
export const removeRenderedPendingEntries = (
	pendingEntries,
	renderedEntries
) => {
	const remaining = { ...pendingEntries };

	renderedEntries.forEach(
		( entry ) => delete remaining[ entryKey( entry ) ]
	);

	return remaining;
};
