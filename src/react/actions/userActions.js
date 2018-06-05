import types from './actionTypes';

export const entryEditOpen = payload => ({
  type: types.ENTRY_EDIT_OPEN,
  payload,
});

export const entryEditClose = payload => ({
  type: types.ENTRY_EDIT_CLOSE,
  payload,
});

export const scrollToEntry = payload => ({
  type: types.SCROLL_TO_ENTRY,
  payload,
});

export const resetScrollOnEntry = payload => ({
  type: types.RESET_SCROLL_ON_ENTRY,
  payload,
});
