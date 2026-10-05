const getEntryKey = ( entry ) => {
	if ( ! entry || entry.id === undefined || entry.id === null ) {
		return false;
	}

	return `id_${ entry.id }`;
};

const hasEntryKey = ( lookup, key ) =>
	Boolean( key ) && Object.prototype.hasOwnProperty.call( lookup, key );

/**
 * Return polling `new` entries whose logical IDs are not already known.
 *
 * Deduplication is deliberately based on entry identity, not timestamp
 * ordering. Repeated IDs within the same response are also collapsed.
 *
 * @param {Array}  entries       Polling response entries.
 * @param {Object} knownEntryIds Logical entry IDs already known to the client.
 * @return {Array} New entries that still need to be queued.
 */
export const filterKnownNewEntries = ( entries, knownEntryIds = {} ) => {
	const newIdsInBatch = {};

	return entries.filter( ( entry ) => {
		if ( entry.type !== 'new' ) {
			return false;
		}

		const key = getEntryKey( entry );

		if ( ! key ) {
			return true;
		}

		if (
			hasEntryKey( knownEntryIds, key ) ||
			hasEntryKey( newIdsInBatch, key )
		) {
			return false;
		}

		newIdsInBatch[ key ] = true;
		return true;
	} );
};

/**
 * Add selected logical IDs to the known-entry lookup, cloning at most once.
 *
 * @param {Object}   knownEntryIds  Logical entry IDs already known to the client.
 * @param {Array}    entries        Entries received in the current batch.
 * @param {Function} shouldRemember Whether the entry proves its ID is known.
 * @return {Object} Updated known-entry ID lookup.
 */
const rememberEntryIds = ( knownEntryIds = {}, entries, shouldRemember ) => {
	let nextKnownEntryIds = knownEntryIds;

	entries.forEach( ( entry ) => {
		if ( ! shouldRemember( entry ) ) {
			return;
		}

		const key = getEntryKey( entry );

		if ( ! key || hasEntryKey( nextKnownEntryIds, key ) ) {
			return;
		}

		if ( nextKnownEntryIds === knownEntryIds ) {
			nextKnownEntryIds = { ...knownEntryIds };
		}

		nextKnownEntryIds[ key ] = true;
	} );

	return nextKnownEntryIds;
};

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
	rememberEntryIds(
		knownEntryIds,
		entries,
		( entry ) => entry.type === 'new' || entry.type === 'update'
	);

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
	rememberEntryIds(
		knownEntryIds,
		entries,
		( entry ) => entry.type === 'new'
	);

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
	let nextPendingEntries = pendingEntries;

	renderedEntries.forEach( ( entry ) => {
		if (
			entry.type !== 'new' &&
			entry.type !== 'update' &&
			entry.type !== 'delete'
		) {
			return;
		}

		const key = getEntryKey( entry );

		if ( ! key || ! hasEntryKey( nextPendingEntries, key ) ) {
			return;
		}

		if ( nextPendingEntries === pendingEntries ) {
			nextPendingEntries = { ...pendingEntries };
		}

		delete nextPendingEntries[ key ];
	} );

	return nextPendingEntries;
};
