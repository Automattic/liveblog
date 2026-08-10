/**
 * Remove polling entries that are already known to the client as new entries.
 *
 * Polling URLs are rounded to cache buckets, so a response may include entries
 * from before the client's current polling baseline. Updates and deletes must
 * still pass through because they represent new changes to existing entries.
 *
 * @param {Array}        entries         Polling response entries.
 * @param {Object}       renderedEntries Entries currently rendered by the API reducer.
 * @param {Object|false} newestEntry     Newest entry known when polling starts.
 * @return {Array} Polling entries that still need to be processed.
 */
export const filterKnownNewEntries = (
	entries,
	renderedEntries,
	newestEntry
) =>
	entries.filter( ( entry ) => {
		if ( entry.type !== 'new' ) {
			return true;
		}

		if (
			Object.prototype.hasOwnProperty.call(
				renderedEntries,
				`id_${ entry.id }`
			)
		) {
			return false;
		}

		if ( ! newestEntry || ! entry.timestamp || ! newestEntry.timestamp ) {
			return true;
		}

		const entryTimestamp = parseInt( entry.timestamp, 10 );
		const newestTimestamp = parseInt( newestEntry.timestamp, 10 );

		if (
			! Number.isFinite( entryTimestamp ) ||
			! Number.isFinite( newestTimestamp )
		) {
			return true;
		}

		if ( entryTimestamp < newestTimestamp ) {
			return false;
		}

		if ( entryTimestamp > newestTimestamp ) {
			return true;
		}

		const entryId = parseInt( entry.id, 10 );
		const newestId = parseInt( newestEntry.id, 10 );

		if ( ! Number.isFinite( entryId ) || ! Number.isFinite( newestId ) ) {
			return true;
		}

		/*
		 * A runtime update/delete exposes the replaced entry ID, not the newer
		 * comment ID, so IDs cannot safely order same-second entries in that case.
		 * A missing type is the initial config baseline, where latest_entry_id is
		 * the newest comment ID and can be compared safely.
		 */
		if ( newestEntry.type && newestEntry.type !== 'new' ) {
			return true;
		}

		return entryId > newestId;
	} );
