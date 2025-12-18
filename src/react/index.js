import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import configureStore from './store';
import AppContainer from './containers/AppContainer';

import '../styles/core.scss';

const store = configureStore();

/* eslint-disable camelcase, no-undef */
__webpack_public_path__ = `${window.liveblog_settings.plugin_dir}assets/`;

const container = document.getElementById('wpcom-liveblog-container');
if (container) {
  const root = createRoot(container);
  root.render(
    <Provider store={store}>
      <AppContainer />
    </Provider>,
  );
}
