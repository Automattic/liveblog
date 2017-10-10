import {
  entriesApplyUpdate,
  getLastOfObject,
  getFirstOfObject,
  getOldestTimestamp,
  getNewestTimestamp,
} from '../utils/utils';

export const initialState = {
  entries: {},
  error: false,
  polling: [],
  previousPolling: [],
  newestEntryTimestamp: false,
  oldestEntryTimestamp: false,
  nonce: false,
  timestamp: false,
};

export const api = (state = initialState, action) => {
  switch (action.type) {
    case 'GET_ENTRIES':
      return {
        ...state,
        error: false,
      };

    case 'GET_ENTRIES_SUCCESS':
      return {
        ...state,
        error: false,
        entries: entriesApplyUpdate(state.entries, action.payload.entries, true),
        oldestEntryTimestamp: getOldestTimestamp(state.oldestEntryTimestamp, action.payload.entries),
        newestEntryTimestamp: getNewestTimestamp(state.newestEntryTimestamp, action.payload.entries),
      };

    case 'GET_ENTRIES_FAILED':
      return {
        ...state,
        error: true,
      };

    case 'START_POLLING':
      return {
        ...state,
        timestamp: parseInt(action.payload, 10),
        error: false,
      };

    case 'POLLING_SUCCESS':
      return {
        ...state,
        timestamp: action.incrementTimestamp ? parseInt(state.timestamp, 10) + 3 : parseInt(state.timestamp, 10),
        entries: entriesApplyUpdate(state.entries, action.payload.entries, false),
        newestEntryTimestamp: getNewestTimestamp(state.newestEntryTimestamp, action.payload.entries),
        error: false,
      };

    case 'POLLING_FAILED':
      return {
        ...state,
        error: true,
      };

    case 'CREATE_ENTRY_SUCCESS':
      return {
        ...state,
        error: false,
        lastEntry: {
          id: action.payload.id,
          updated: action.payload.updated,
        },
        nonce: action.payload.nonce,
      };

    case 'CREATE_ENTRY_FAILED':
      return {
        ...state,
        error: true,
      };

    case 'DELETE_ENTRY_SUCCESS':
      return {
        ...state,
        error: false,
        lastEntry: {
          id: action.payload.id,
          updated: action.payload.updated,
        },
        nonce: action.payload.nonce,
      };

    case 'DELETE_ENTRY_FAILED':
      return {
        ...state,
        error: true,
      };

    case 'UPDATE_ENTRY_SUCCESS':
      return {
        ...state,
        error: false,
        lastEntry: {
          id: action.payload.id,
          updated: action.payload.updated,
        },
        nonce: action.payload.nonce,
      };

    case 'UPDATE_ENTRY_FAILED':
      return {
        ...state,
        error: true,
      };

    default:
      return state;
  }
};
