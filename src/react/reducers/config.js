import {
  getCurrentTimestamp,
} from '../utils/utils';

export const initialState = {};

export const config = (state = initialState, action) => {
  switch (action.type) {
    case 'LOAD_CONFIG':
      return {
        ...state,
        ...action.payload,
        timeDifference: getCurrentTimestamp() - action.payload.timestamp,
      };

    default:
      return state;
  }
};
