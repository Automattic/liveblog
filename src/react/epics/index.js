import 'rxjs';
import { combineEpics } from 'redux-observable';
import * as apiEpics from  './api'

export default combineEpics(
  apiEpics.epics,
);