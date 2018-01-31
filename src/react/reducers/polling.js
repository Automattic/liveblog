import {
  applyUpdate,
  getNewestEntry,
} from '../utils/utils';

export const initialState = {
  error: false,
  newestEntry: false,
  entries: {},
  pages: 1,
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
        newestEntry: getNewestEntry(
          state.newestEntry,
          action.payload.entries[action.payload.entries.length - 1],
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

    default:
      return state;
  }
};
