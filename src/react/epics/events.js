import { combineEpics } from 'redux-observable';
import { of, concat } from 'rxjs';
import { switchMap, timeout, map, catchError, mergeMap } from 'rxjs/operators';

import types from '../actions/actionTypes';

import {
  getEventsSuccess,
  getEventsFailed,
  deleteEventSuccess,
  deleteEventFailed,
} from '../actions/eventsActions';

import {
  getEntriesSuccess,
  getEntriesFailed,
} from '../actions/apiActions';

import {
  scrollToEntry,
} from '../actions/userActions';

import {
  getEvents,
  deleteEvent,
  jumpToEvent,
} from '../services/api';

const getEventsEpic = (action$, store) =>
  action$.ofType(types.GET_EVENTS).pipe(
    switchMap(() =>
      getEvents(store.getState().config, store.getState().api.newestEntry).pipe(
        timeout(10000),
        map(res => getEventsSuccess(res.response)),
        catchError(error => of(getEventsFailed(error))),
      ),
    ),
  );

const deleteEventEpic = (action$, store) =>
  action$.ofType(types.DELETE_EVENT).pipe(
    switchMap(({ payload }) =>
      deleteEvent(payload, store.getState().config).pipe(
        timeout(10000),
        map(res => deleteEventSuccess(res.response)),
        catchError(error => of(deleteEventFailed(error))),
      ),
    ),
  );

const jumpToEventEpic = (action$, store) =>
  action$.ofType(types.JUMP_TO_EVENT).pipe(
    switchMap(({ payload }) =>
      jumpToEvent(payload, store.getState().config, store.getState().api.newestEntry).pipe(
        timeout(10000),
        mergeMap((res) => {
          if (!res.response.entries.some(x => x.id === payload)) {
            return of(getEntriesSuccess(res.response));
          }
          return concat(
            of(getEntriesSuccess(res.response)),
            of(scrollToEntry(`id_${payload}`)),
          );
        }),
        catchError(error => of(getEntriesFailed(error))),
      ),
    ),
  );

export default combineEpics(
  getEventsEpic,
  deleteEventEpic,
  jumpToEventEpic,
);
