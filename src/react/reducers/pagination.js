export const initialState = {
  page: 1,
  pages: 1,
  entriesPerPage: 0,
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
        pages: action.payload.pages,
        page: action.payload.page,
        entriesPerPage: action.payload.entries.length,
      };

    case 'POLLING_SUCCESS':
      return {
        ...state,
        pages: action.renderNewEntries
          ? action.payload.pages || state.pages
          : state.pages,
      };

    default:
      return state;
  }
};
