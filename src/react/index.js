import React from 'react';
import ReactDOM from 'react-dom';
import { Provider } from 'react-redux';
import configureStore from './store';
import AppContainer from './containers/AppContainer';

import '../styles/core.scss';
import '../styles/theme.scss';

const store = configureStore();

ReactDOM.render(
  <Provider store={store}>
    <AppContainer />
  </Provider>,
  document.getElementById('wpcom-liveblog-container'),
);
