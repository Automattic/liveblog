import { combineEpics } from 'redux-observable';
import { of } from 'rxjs/observable/of';
import { concat } from 'rxjs/observable/concat';

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
  action$.ofType(types.GET_EVENTS)
    .switchMap(() =>
      getEvents(store.getState().config, store.getState().api.newestEntry)
        .timeout(10000)
        .map(res => getEventsSuccess(res.response))
        .catch(error => of(getEventsFailed(error))),
    );

const deleteEventEpic = (action$, store) =>
  action$.ofType(types.DELETE_EVENT)
    .switchMap(({ payload }) =>
      deleteEvent(payload, store.getState().config)
        .timeout(10000)
        .map(res => deleteEventSuccess(res.response))
        .catch(error => of(deleteEventFailed(error))),
    );

const jumpToEventEpic = (action$, store) =>
  action$.ofType(types.JUMP_TO_EVENT)
    .switchMap(({ payload }) =>
      jumpToEvent(payload, store.getState().config, store.getState().api.newestEntry)
        .timeout(10000)
        .flatMap((res) => {
          if (!res.response.entries.some(x => x.id === payload)) {
            return of(getEntriesSuccess(res.response));
          }
          return concat(
            of(getEntriesSuccess(res.response)),
            of(scrollToEntry(`id_${payload}`)),
          );
        })
        .catch(error => of(getEntriesFailed(error))),
    );

export default combineEpics(
  getEventsEpic,
  deleteEventEpic,
  jumpToEventEpic,
);
