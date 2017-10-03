import types from './actionTypes';

export const entryEditOpen = payload => ({
  type: types.ENTRY_EDIT_OPEN,
  payload,
});

export const entryEditClose = payload => ({
  type: types.ENTRY_EDIT_CLOSE,
  payload,
});
