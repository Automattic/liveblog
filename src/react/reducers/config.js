export const initialState = {};

export const config = (state = initialState, action) => {
  switch (action.type) {
    case 'LOAD_CONFIG':
      return {
        ...state,
        ...action.payload,
      };

    default:
      return state;
  }
};
