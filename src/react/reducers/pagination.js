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

    case 'POLLING_SUCCESS':
      return {
        ...state,
        pages: action.renderNewEntries
          ? Math.max(action.payload.pages, 1) || state.pages
          : state.pages,
      };

    default:
      return state;
  }
};
