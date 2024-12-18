import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
// import Polyfills from './polyfills/index';
import configureStore from './store';
import AppContainer from './containers/AppContainer';

import '../styles/core.scss';

// Polyfills();

const store = configureStore();
const container = document.getElementById('wpcom-liveblog-container');
const root = createRoot(container);

/* eslint-disable camelcase, no-undef */
__webpack_public_path__ = `${window.liveblog_settings.plugin_dir}assets/`;

root.render(
  <Provider store={store}>
    <AppContainer />
  </Provider>
);
