import {
  applyUpdate,
  getNewestEntry,
} from '../utils/utils';

export const initialState = {
  error: false,
  newestEntry: false,
  entries: {},
};

export const polling = (state = initialState, action) => {
  switch (action.type) {
    case 'POLLING_SUCCESS':
      return {
        ...state,
        error: false,
        entries: action.renderNewEntries
          ? {}
          : applyUpdate(
            state.entries,
            action.payload.entries.filter(entry => entry.type === 'new'),
          ),
        newestEntry: getNewestEntry(state.newestEntry, action.payload.entries[0]),
      };

    case 'POLLING_FAILED':
      return {
        ...state,
        error: true,
      };

    case 'GET_ENTRIES_SUCCESS':
      return {
        ...state,
        newestEntry: getNewestEntry(
          state.newestEntry,
          action.payload.entries[0],
        ),
        entries: action.renderNewEntries
          ? {}
          : state.entries,
      };

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
          timestamp: parseInt(action.payload.latest_entry_timestamp, 10),
        },
      };

    default:
      return state;
  }
};
