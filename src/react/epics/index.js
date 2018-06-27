// RxJS operators
import 'rxjs/add/operator/switchMap';
import 'rxjs/add/operator/map';
import 'rxjs/add/operator/timeout';
import 'rxjs/add/operator/catch';
import 'rxjs/add/operator/takeUntil';
import 'rxjs/add/operator/exhaustMap';
import 'rxjs/add/operator/mergeMap';

import { combineEpics } from 'redux-observable';
import apiEpics from './api';
import pollingEpics from './polling';
import eventsEpics from './events';

export default combineEpics(
  apiEpics,
  pollingEpics,
  eventsEpics,
);
