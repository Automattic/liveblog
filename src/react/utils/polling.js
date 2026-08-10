/**
 * Remove polling entries that are already rendered as new entries.
 *
 * Polling URLs are rounded to cache buckets, so a response may include an
 * entry that the client has already rendered. Updates and deletes must still
 * pass through because they represent new changes to an existing entry.
 *
 * @param {Array}  entries         Polling response entries.
 * @param {Object} renderedEntries Entries currently rendered by the API reducer.
 * @return {Array} Polling entries that still need to be processed.
 */
export const filterRenderedNewEntries = ( entries, renderedEntries ) =>
	entries.filter( ( entry ) => {
		if ( entry.type !== 'new' ) {
			return true;
		}

		return ! Object.prototype.hasOwnProperty.call(
			renderedEntries,
			`id_${ entry.id }`
		);
	} );
