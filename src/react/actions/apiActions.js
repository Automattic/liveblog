import types from './actionTypes';

export const getEntries = (page, hash = false) => ({
  type: types.GET_ENTRIES,
  page,
  hash,
});

export const getEntriesPaginated = (page, scrollTo) => ({
  type: types.GET_ENTRIES_PAGINATED,
  page,
  scrollTo,
});

export const getEntriesSuccess = (payload, renderNewEntries) => ({
  type: types.GET_ENTRIES_SUCCESS,
  payload,
  renderNewEntries,
});

export const getEntriesFailed = () => ({
  type: types.GET_ENTRIES_FAILED,
  error: true,
});

export const startPolling = payload => ({
  type: types.START_POLLING,
  payload,
});

export const pollingSuccess = (payload, renderNewEntries) => ({
  type: types.POLLING_SUCCESS,
  payload,
  renderNewEntries,
});

export const pollingFailed = () => ({
  type: types.POLLING_FAILED,
  error: true,
});

export const cancelPolling = () => ({
  type: types.CANCEL_POLLING,
});

export const createEntry = payload => ({
  type: types.CREATE_ENTRY,
  payload,
});

export const createEntrySuccess = payload => ({
  type: types.CREATE_ENTRY_SUCCESS,
  payload,
});

export const createEntryFailed = () => ({
  type: types.CREATE_ENTRY_FAILED,
  error: true,
});

export const deleteEntry = payload => ({
  type: types.DELETE_ENTRY,
  payload,
});

export const deleteEntrySuccess = payload => ({
  type: types.DELETE_ENTRY_SUCCESS,
  payload,
});

export const deleteEntryFailed = () => ({
  type: types.DELETE_ENTRY_FAILED,
  error: true,
});

export const updateEntry = payload => ({
  type: types.UPDATE_ENTRY,
  payload,
});

export const updateEntrySuccess = payload => ({
  type: types.UPDATE_ENTRY_SUCCESS,
  payload,
});

export const updateEntryFailed = () => ({
  type: types.UPDATE_ENTRY_FAILED,
  error: true,
});

export const mergePolling = () => ({
  type: types.MERGE_POLLING,
});

export const mergePollingIntoEntries = (payload, pages) => ({
  type: types.MERGE_POLLING_INTO_ENTRIES,
  payload,
  pages,
});

