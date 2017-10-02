import { applyMiddleware, createStore, combineReducers } from 'redux';
import { createEpicMiddleware } from 'redux-observable';
import { composeWithDevTools } from 'redux-devtools-extension';
import rootReducer from '../reducers';
import rootEpic from '../epics';

function configureStore(initialState) {
  const epicMiddleware = createEpicMiddleware(rootEpic);

  const enhancers = composeWithDevTools(
    applyMiddleware(
      epicMiddleware,
    ),
  );

  const store = createStore(
    combineReducers(rootReducer),
    initialState,
    enhancers,
  );

  /**
   * For hot reloading.
   */
  // if (module.hot) {
  //   module.hot.accept(() => {
  //     store.replaceReducer(require('../reducers').default);
  //   });
  // }

  return store;
}

export default configureStore;
