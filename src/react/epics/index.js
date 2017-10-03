import 'rxjs';
import { combineEpics } from 'redux-observable';
import apiEpics from './api';

export default combineEpics(
  apiEpics,
);
