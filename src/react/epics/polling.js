import { combineEpics } from 'redux-observable';
import { of } from 'rxjs/observable/of';
import { interval } from 'rxjs/observable/interval';
import { concat } from 'rxjs/observable/concat';
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
  action$.ofType(types.START_POLLING)
    .switchMap(() =>
      interval(3000)
        .takeUntil(action$.ofType(types.CANCEL_POLLING))
        .exhaustMap(() =>
          pollingApi(store.getState().polling.newestEntry.timestamp, store.getState().config)
            .timeout(10000)
            .map(res =>
              pollingSuccess(
                res.response,
                shouldRenderNewEntries(
                  store.getState().pagination.page,
                  store.getState().api.entries,
                  store.getState().polling.entries,
                ),
              ),
            )
            .catch(error => of(pollingFailed(error))),
        ),
    );

const mergePollingEpic = (action$, store) =>
  action$.ofType(types.MERGE_POLLING)
    .switchMap(() => {
      const { pagination, polling, config } = store.getState();

      if (pagination.page === 1) {
        return concat(
          of(mergePollingIntoEntries(polling.entries)),
          of(scrollToEntry(`id_${polling.newestEntry.id}`)),
        );
      }

      return getEntries(1, config, polling.newestEntry)
        .timeout(10000)
        .flatMap(res => concat(
          of(getEntriesSuccess(res.response, true)),
          of(scrollToEntry(`id_${polling.newestEntry.id}`)),
        ))
        .catch(error => of(getEntriesFailed(error)));
    });

export default combineEpics(
  startPollingEpic,
  mergePollingEpic,
);
