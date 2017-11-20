import { applyMiddleware, createStore, combineReducers } from 'redux';
import { createEpicMiddleware } from 'redux-observable';
import { composeWithDevTools } from 'redux-devtools-extension';
import updatePollingInterval from '../middleware/updatePollingInterval';
import rootReducer from '../reducers';
import rootEpic from '../epics';

function configureStore(initialState) {
  const epicMiddleware = createEpicMiddleware(rootEpic);

  const enhancers = composeWithDevTools(
    applyMiddleware(
      epicMiddleware,
      updatePollingInterval,
    ),
  );

  const store = createStore(
    combineReducers(rootReducer),
    initialState,
    enhancers,
  );

  return store;
}

export default configureStore;
