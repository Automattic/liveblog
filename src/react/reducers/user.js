export const initialState = {
  entries: {},
};

export const user = (state = initialState, action) => {
  switch (action.type) {
    case 'ENTRY_EDIT_OPEN':
      return {
        ...state,
        entries: {
          ...state.entries,
          [action.payload]: {
            isEditing: true,
            isPublishing: false,
          },
        },
      };

    case 'ENTRY_EDIT_CLOSE':
      return {
        ...state,
        entries: {
          ...state.entries,
          [action.payload]:
          {
            isEditing: false,
            isPublishing: false,
          },
        },
      };

    case 'UPDATE_ENTRY':
      return {
        ...state,
        entries: {
          ...state.entries,
          [action.payload.id]: {
            isEditing: true,
            isPublishing: true,
          },
        },
      };

    case 'UPDATE_ENTRY_SUCCESS':
      return {
        ...state,
        entries: {
          ...state.entries,
          [action.payload.entries[0].id]: {
            isEditing: false,
            isPublishing: false,
          },
        },
      };

    default:
      return state;
  }
};
