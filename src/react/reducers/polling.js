import { applyUpdate, getNewestEntry } from '../utils/utils';
import {
	filterKnownNewEntries,
	rememberPolledEntries,
	rememberRenderedEntries,
	removeRenderedPendingEntries,
} from '../utils/polling';

export const initialState = {
	error: false,
	newestEntry: false,
	entries: {},
	knownEntryIds: {},
	pages: 1,
};

export const polling = ( state = initialState, action ) => {
	switch ( action.type ) {
		case 'POLLING_SUCCESS':
			return {
				...state,
				error: false,
				entries: action.renderNewEntries
					? {}
					: applyUpdate(
							state.entries,
							filterKnownNewEntries(
								action.payload.entries,
								state.knownEntryIds
							)
					  ),
				knownEntryIds: rememberPolledEntries(
					state.knownEntryIds,
					action.payload.entries
				),
				newestEntry: getNewestEntry(
					state.newestEntry,
					action.payload.entries[ action.payload.entries.length - 1 ]
				),
				pages: action.payload.pages
					? action.payload.pages
					: state.pages,
			};

		case 'POLLING_FAILED':
			return {
				...state,
				error: true,
			};

		case 'GET_ENTRIES_SUCCESS': {
			const knownEntryIds = rememberRenderedEntries(
				state.knownEntryIds,
				action.payload.entries
			);

			return {
				...state,
				newestEntry: getNewestEntry(
					state.newestEntry,
					action.payload.entries[ 0 ]
				),
				entries: action.renderNewEntries
					? {}
					: removeRenderedPendingEntries(
							state.entries,
							action.payload.entries
					  ),
				knownEntryIds,
			};
		}

		case 'MERGE_POLLING_INTO_ENTRIES':
			return {
				...state,
				entries: {},
			};

		case 'LOAD_CONFIG':
			return {
				...state,
				newestEntry: {
					id: action.payload.latest_entry_id,
					timestamp: parseInt(
						action.payload.latest_entry_timestamp,
						10
					),
				},
			};

		default:
			return state;
	}
};
