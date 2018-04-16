import 'rxjs';
import { combineEpics } from 'redux-observable';
import apiEpics from './api';
import pollingEpics from './polling';
import eventsEpics from './events';

export default combineEpics(
  apiEpics,
  pollingEpics,
  eventsEpics,
);
