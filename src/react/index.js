/* eslint-disable camelcase, no-undef */
// Set webpack public path BEFORE any imports that might trigger dynamic chunk loading
__webpack_public_path__ = `${window.liveblog_settings.plugin_dir}assets/`;

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import configureStore from './store';
import AppContainer from './containers/AppContainer';

import '../styles/core.scss';

const store = configureStore();

const container = document.getElementById('wpcom-liveblog-container');
if (container) {
  const root = createRoot(container);
  root.render(
    <Provider store={store}>
      <AppContainer />
    </Provider>,
  );
}
