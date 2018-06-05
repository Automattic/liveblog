export const initialState = {
  entries: {},
};

export const user = (state = initialState, action) => {
  switch (action.type) {
    case 'ENTRY_EDIT_OPEN':
      return {
        ...state,
        entries: { ...state.entries, [action.payload]: { isEditing: true } },
      };

    case 'ENTRY_EDIT_CLOSE':
      return {
        ...state,
        entries: { ...state.entries, [action.payload]: { isEditing: false } },
      };

    default:
      return state;
  }
};
