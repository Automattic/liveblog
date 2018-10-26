import React from 'react';
import ReactDOM from 'react-dom';
import { Provider } from 'react-redux';
import Polyfills from './polyfills/index';
import configureStore from './store';
import AppContainer from './containers/AppContainer';

import '../styles/core.scss';

Polyfills();

const store = configureStore();

/* eslint-disable camelcase, no-undef */
__webpack_public_path__ = `${window.liveblog_settings.plugin_dir}assets/`;

ReactDOM.render(
  <Provider store={store}>
    <AppContainer />
  </Provider>,
  document.getElementById('wpcom-liveblog-container'),
);
