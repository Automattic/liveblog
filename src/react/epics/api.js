import { combineEpics } from 'redux-observable';
import { of } from 'rxjs/observable/of';
import { interval } from 'rxjs/observable/interval';
import types from '../actions/actionTypes';

import {
  getEntriesSuccess,
  getEntriesFailed,
  pollingSuccess,
  pollingFailed,
  createEntrySuccess,
  createEntryFailed,
  updateEntrySuccess,
  updateEntryFailed,
  deleteEntrySuccess,
  deleteEntryFailed,
} from '../actions/apiActions';

import {
  getEntries,
  startPolling,
  createEntry,
  updateEntry,
  deleteEntry,
  examinePolling,
  getEntriesAfterCRUD,
} from '../services/api';

const getEntriesEpic = (action$, store) =>
  action$.ofType(types.GET_ENTRIES)
    .switchMap(({ payload }) =>
      getEntries(payload, store.getState().config)
        .timeout(10000)
        .map(res => getEntriesSuccess(res.response))
        .catch(error => of(getEntriesFailed(error))),
    );

const startPollingEpic = (action$, store) =>
  action$.ofType(types.START_POLLING)
    .switchMap(() =>
      interval(3000)
        .takeUntil(action$.ofType(types.CANCEL_POLLING))
        .exhaustMap(() =>
          startPolling(store.getState().api.timestamp, store.getState().config)
            .timeout(10000)
            .map(res => pollingSuccess(res.response))
            .catch(error => of(pollingFailed(error))),
        ),
    );

const createEntryEpic = (action$, store) =>
  action$.ofType(types.CREATE_ENTRY)
    .switchMap(({ payload }) =>
      createEntry(payload, store.getState().config, store.getState().api.nonce)
        .timeout(10000)
        .map(res => createEntrySuccess(res.response))
        .catch(error => of(createEntryFailed(error))),
    );

const updateEntryEpic = (action$, store) =>
  action$.ofType(types.UPDATE_ENTRY)
    .switchMap(({ payload }) =>
      updateEntry(payload, store.getState().config, store.getState().api.nonce)
        .timeout(10000)
        .map(res => updateEntrySuccess(res.response))
        .catch(error => of(updateEntryFailed(error))),
    );

const deleteEntryEpic = (action$, store) =>
  action$.ofType(types.DELETE_ENTRY)
    .switchMap(({ payload }) =>
      deleteEntry(payload, store.getState().config, store.getState().api.nonce)
        .timeout(10000)
        .map(res => deleteEntrySuccess(res.response))
        .catch(error => of(deleteEntryFailed(error))),
    );

const examinePollingEpic = (action$, store) =>
  action$.ofType(types.POLLING_SUCCESS)
    .switchMap(() => examinePolling(store));

const getEntriesAfterCreateEpic = (action$, store) =>
  action$.ofType(types.CREATE_ENTRY_SUCCESS)
    .switchMap(() => getEntriesAfterCRUD(store));

const getEntriesAfterUpdateEpic = (action$, store) =>
  action$.ofType(types.UPDATE_ENTRY_SUCCESS)
    .switchMap(() => getEntriesAfterCRUD(store));

const getEntriesAfterDeleteEpic = (action$, store) =>
  action$.ofType(types.DELETE_ENTRY_SUCCESS)
    .switchMap(() => getEntriesAfterCRUD(store));

/* eslint-disable */
export const epics = combineEpics(
  getEntriesEpic,
  startPollingEpic,
  createEntryEpic,
  updateEntryEpic,
  deleteEntryEpic,
  examinePollingEpic,
  getEntriesAfterCreateEpic,
  getEntriesAfterUpdateEpic,
  getEntriesAfterDeleteEpic,
);
/* eslint-enable */

