import React from 'react';
import ReactDOM from 'react-dom';
import { Provider } from 'react-redux';
import Polyfills from './polyfills/index';
import configureStore from './store';
import AppContainer from './containers/AppContainer';

import '../styles/core.scss';

Polyfills();

const store = configureStore();
const placeholder = document.getElementById('wpcom-liveblog-container');

if (placeholder) {
  ReactDOM.render(
    <Provider store={store}>
      <AppContainer />
    </Provider>,
    placeholder,
  );
}
