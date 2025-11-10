/* eslint-disable import/prefer-default-export */

import { combineEpics } from 'redux-observable';
import { of, concat } from 'rxjs';
import { switchMap, timeout, map, catchError, mergeMap } from 'rxjs/operators';

import types from '../actions/actionTypes';

import {
  getEntriesSuccess,
  getEntriesFailed,
  pollingSuccess,
  createEntrySuccess,
  createEntryFailed,
  updateEntrySuccess,
  updateEntryFailed,
  deleteEntrySuccess,
  deleteEntryFailed,
} from '../actions/apiActions';

import {
  jumpToEvent,
} from '../actions/eventsActions';

import {
  getEntries,
  createEntry,
  updateEntry,
  deleteEntry,
} from '../services/api';

import {
  shouldRenderNewEntries,
  getScrollToId,
} from '../utils/utils';

import {
  scrollToEntry,
} from '../actions/userActions';

const getEntriesEpic = (action$, store) =>
  action$.ofType(types.GET_ENTRIES).pipe(
    switchMap(({ page, hash }) => {
      /**
       * If there is a has in the url, we check that it is a number
       * and then we jump to the that entry. If the number isn't a valid
       * id of an entry the jumpToEvent api will return the first page.
       */
      if (hash) {
        const id = hash.split('#')[1];
        if (!isNaN(id)) return of(jumpToEvent(id));
      }

      return getEntries(page, store.getState().config, store.getState().api.newestEntry).pipe(
        timeout(10000),
        map(res =>
          getEntriesSuccess(
            res.response,
            shouldRenderNewEntries(
              store.getState().pagination.page,
              store.getState().api.entries,
              store.getState().polling.entries,
            ),
          ),
        ),
        catchError(error => of(getEntriesFailed(error))),
      );
    }),
  );

const getPaginatedEntriesEpic = (action$, store) =>
  action$.ofType(types.GET_ENTRIES_PAGINATED).pipe(
    switchMap(({ page, scrollTo }) =>
      getEntries(page, store.getState().config, store.getState().api.newestEntry).pipe(
        timeout(10000),
        mergeMap(res =>
          concat(
            of(getEntriesSuccess(
              res.response,
              shouldRenderNewEntries(
                store.getState().pagination.page,
                store.getState().api.entries,
                store.getState().polling.entries,
              ),
            )),
            of(scrollToEntry(getScrollToId(res.response.entries, scrollTo))),
          ),
        ),
        catchError(error => of(getEntriesFailed(error))),
      ),
    ),
  );

const createEntryEpic = (action$, store) =>
  action$.ofType(types.CREATE_ENTRY).pipe(
    switchMap(({ payload }) =>
      createEntry(payload, store.getState().config, store.getState().api.nonce).pipe(
        timeout(10000),
        map(res => createEntrySuccess(res.response)),
        catchError(error => of(createEntryFailed(error))),
      ),
    ),
  );

const updateEntryEpic = (action$, store) =>
  action$.ofType(types.UPDATE_ENTRY).pipe(
    switchMap(({ payload }) =>
      updateEntry(payload, store.getState().config, store.getState().api.nonce).pipe(
        timeout(10000),
        map(res => updateEntrySuccess(res.response)),
        catchError(error => of(updateEntryFailed(error))),
      ),
    ),
  );

const deleteEntryEpic = (action$, store) =>
  action$.ofType(types.DELETE_ENTRY).pipe(
    switchMap(({ payload }) =>
      deleteEntry(payload, store.getState().config, store.getState().api.nonce).pipe(
        timeout(10000),
        map(res => deleteEntrySuccess(res.response)),
        catchError(error => of(deleteEntryFailed(error))),
      ),
    ),
  );

const getEntriesAfterChangeEpic = action$ =>
  action$.ofType(types.CREATE_ENTRY_SUCCESS, types.UPDATE_ENTRY_SUCCESS, types.DELETE_ENTRY_SUCCESS).pipe(
    map(({ payload }) => pollingSuccess(payload, true)),
  );

export default combineEpics(
  getEntriesEpic,
  getPaginatedEntriesEpic,
  createEntryEpic,
  updateEntryEpic,
  deleteEntryEpic,
  getEntriesAfterChangeEpic,
);
