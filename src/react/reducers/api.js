/* global jQuery */

import {
  applyUpdate,
  pollingApplyUpdate,
  getNewestEntry,
} from '../utils/utils';

export const initialState = {
  loading: false,
  error: false,
  entries: {},
  newestEntry: false,
  nonce: false,
  total: 0,
  status: 'any',
};

export const api = (state = initialState, action) => {
  switch (action.type) {
    case 'JUMP_TO_EVENT':
    case 'GET_ENTRIES':
    case 'GET_ENTRIES_PAGINATED':
      return {
        ...state,
        error: false,
        loading: true,
      };

    case 'GET_ENTRIES_SUCCESS':
      return {
        ...state,
        error: false,
        loading: false,
        entries: applyUpdate(
          action.paginationType === 'loadMore' ? state.entries : {},
          action.payload.entries,
        ),
        newestEntry: getNewestEntry(
          state.newestEntry,
          action.payload.entries[0],
        ),
        total: action.payload.total,
      };

    case 'GET_ENTRIES_FAILED':
      return {
        ...state,
        loading: false,
        error: true,
      };

    case 'POLLING_SUCCESS':
      if (action.payload.entries && action.payload.entries.length !== 0) {
        jQuery(document).trigger('liveblog-post-update', [action.payload]);
      }
      return {
        ...state,
        error: false,
        entries: pollingApplyUpdate(
          state.entries,
          action.payload.entries,
          action.renderNewEntries,
        ),
        newestEntry: action.renderNewEntries
          ? getNewestEntry(
            state.newestEntry,
            action.payload.entries[action.payload.entries.length - 1],
            state.entries,
          )
          : state.newestEntry,
        total: action.payload.total ? action.payload.total : state.total,
      };

    case 'CREATE_ENTRY_SUCCESS':
      return {
        ...state,
        error: false,
        nonce: action.payload.nonce,
        total: state.total + 1,
      };

    case 'CREATE_ENTRY_FAILED':
      return {
        ...state,
        error: true,
        message: action.message.message,
      };

    case 'DELETE_ENTRY_SUCCESS':
      return {
        ...state,
        error: false,
        nonce: action.payload.nonce,
        total: state.total - 1,
      };

    case 'DELETE_ENTRY_FAILED':
      return {
        ...state,
        error: true,
      };

    case 'UPDATE_ENTRY_SUCCESS': {
      const entries = { ...state.entries };
      const entry = { ...action.payload.entries[0] };
      const id = `id_${entry.id}`;
      entries[id] = entry;

      return {
        ...state,
        error: false,
        entries,
        nonce: action.payload.nonce,
      };
    }
    case 'UPDATE_ENTRY_FAILED':
      return {
        ...state,
        error: true,
        errorMessage: action.message.response.message,
      };

    case 'MERGE_POLLING_INTO_ENTRIES':
      return {
        ...state,
        entries: pollingApplyUpdate(
          state.entries,
          action.payload,
          true,
        ),
        newestEntry: action.payload[action.payload.length - 1],
      };

    case 'SCROLL_TO_ENTRY':
      return state.entries[action.payload]
        ? {
          ...state,
          entries: {
            ...state.entries,
            [action.payload]: {
              ...state.entries[action.payload],
              activateScrolling: true,
            },
          },
        }
        : state;

    case 'RESET_SCROLL_ON_ENTRY':
      return state.entries[action.payload]
        ? {
          ...state,
          entries: {
            ...state.entries,
            [action.payload]: {
              ...state.entries[action.payload],
              activateScrolling: false,
            },
          },
        }
        : state;

    case 'LOAD_CONFIG':
      return {
        ...state,
        newestEntry: {
          id: action.payload.latest_entry_id,
          timestamp: parseInt(action.payload.latest_entry_timestamp, 10),
        },
      };
    case 'SET_STATUS':
      return {
        ...state,
        status: action.payload.status,
      };
    default:
      return state;
  }
};
