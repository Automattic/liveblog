// RxJS operators
// import { switchMap, map, timeout, catchError, takeUntil, exhaustMap, mergeMap } from 'rxjs/operators';

import { combineEpics } from 'redux-observable';
import apiEpics from './api';
import pollingEpics from './polling';
import eventsEpics from './events';

export default combineEpics(
  apiEpics,
  pollingEpics,
  eventsEpics,
);
