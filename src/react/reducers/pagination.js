import {
  getPollingPages,
} from '../utils/utils';

export const initialState = {
  page: 1,
  pages: 1,
};

export const pagination = (state = initialState, action) => {
  switch (action.type) {
    case 'GET_ENTRIES':
      return {
        ...state,
        page: action.page,
      };

    case 'GET_ENTRIES_SUCCESS':
      return {
        ...state,
        pages: Math.max(action.payload.pages, 1),
        page: action.payload.page,
      };

    case 'MERGE_POLLING_INTO_ENTRIES':
      return {
        ...state,
        pages: action.pages,
      };

    case 'POLLING_SUCCESS':
      return {
        ...state,
        pages: action.renderNewEntries
          ? getPollingPages(state.pages, action.payload.pages)
          : state.pages,
      };

    default:
      return state;
  }
};
