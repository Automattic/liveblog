/* eslint-disable import/prefer-default-export */
import types from './actionTypes';

export const loadConfig = payload => ({
  type: types.LOAD_CONFIG,
  payload,
});

export const updateInterval = payload => ({
  type: types.UPDATE_INTERVAL,
  payload,
});
