import React from 'react';
import ReactDOM from 'react-dom';
import { Provider } from 'react-redux';
import Polyfills from './polyfills/index';
import configureStore from './store';
import AppContainer from './containers/AppContainer';

import '../styles/core.scss';

Polyfills();

const store = configureStore();
const el = document.getElementById('wpcom-liveblog-container');

if (el) {
  ReactDOM.render(
    <Provider store={store}>
      <AppContainer />
    </Provider>,
    el,
  );
}
