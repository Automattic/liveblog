import { combineEpics } from 'redux-observable';
import { of, interval, concat } from 'rxjs';
import { switchMap, takeUntil, exhaustMap, timeout, map, catchError, mergeMap } from 'rxjs/operators';
import types from '../actions/actionTypes';

import {
  pollingSuccess,
  pollingFailed,
  mergePollingIntoEntries,
  getEntriesSuccess,
  getEntriesFailed,
} from '../actions/apiActions';

import {
  polling as pollingApi,
  getEntries,
} from '../services/api';

import {
  scrollToEntry,
} from '../actions/userActions';

import {
  shouldRenderNewEntries,
} from '../utils/utils';

const startPollingEpic = (action$, store) =>
  action$.ofType(types.START_POLLING).pipe(
    switchMap(() =>
      interval(store.getState().config.refresh_interval * 1000).pipe(
        takeUntil(action$.ofType(types.CANCEL_POLLING)),
        exhaustMap(() =>
          pollingApi(store.getState().polling.newestEntry.timestamp, store.getState().config).pipe(
            timeout(10000),
            map(res =>
              pollingSuccess(
                res.response,
                shouldRenderNewEntries(
                  store.getState().pagination.page,
                  store.getState().api.entries,
                  store.getState().polling.entries,
                ),
              ),
            ),
            catchError(error => of(pollingFailed(error))),
          ),
        ),
      ),
    ),
  );

const mergePollingEpic = (action$, store) =>
  action$.ofType(types.MERGE_POLLING).pipe(
    switchMap(() => {
      const { pagination, polling, config } = store.getState();
      const entries = Object.keys(polling.entries).map(key => polling.entries[key]);
      const pages = Math.max(pagination.pages, polling.pages);

      if (pagination.page === 1) {
        return concat(
          of(mergePollingIntoEntries(entries, pages)),
          of(scrollToEntry(`id_${entries[entries.length - 1].id}`)),
        );
      }

      return getEntries(1, config, polling.newestEntry).pipe(
        timeout(10000),
        mergeMap(res => concat(
          of(getEntriesSuccess(res.response, true)),
          of(scrollToEntry(`id_${polling.newestEntry.id}`)),
        )),
        catchError(error => of(getEntriesFailed(error))),
      );
    }),
  );

export default combineEpics(
  startPollingEpic,
  mergePollingEpic,
);
