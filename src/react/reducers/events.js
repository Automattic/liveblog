import {
  applyUpdate,
  eventsApplyUpdate,
} from '../utils/utils';

export const initialState = {
  error: false,
  entries: {},
};

export const events = (state = initialState, action) => {
  switch (action.type) {
    case 'GET_EVENTS':
      return {
        ...state,
        error: false,
      };

    case 'GET_EVENTS_SUCCESS':
      return {
        ...state,
        error: false,
        entries: applyUpdate(state.entries, action.payload),
      };

    case 'GET_EVENTS_FAILED':
      return {
        ...state,
        error: true,
      };

    case 'POLLING_SUCCESS':
      return {
        ...state,
        error: false,
        entries: eventsApplyUpdate(state.entries, action.payload.entries),
      };

    case 'DELETE_EVENT_SUCCESS':
      return {
        ...state,
        error: false,
        entries: eventsApplyUpdate(state.entries, action.payload.entries),
      };

    case 'DELETE_EVENT_FAILED':
      return {
        ...state,
        error: true,
      };

    default:
      return state;
  }
};
