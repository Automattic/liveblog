import { getCurrentTimestamp } from '../../utils/utils';
import data from '../../mockData/reducers/config';
import { initialState, config } from '../config';
import { loadConfig, updateInterval } from '../../actions/configActions';

const newState = {
  ...data,
  timeDifference: getCurrentTimestamp() - data.timestamp,
};

describe('config reducer', () => {
  it('should return the initial state', () => {
    expect(config(undefined, {})).toEqual(initialState);
  });

  it('should handle LOAD_CONFIG', () => {
    expect(config(initialState, loadConfig(data))).toEqual(newState);
  });

  it('should handle UPDATE_INTERVAL', () => {
    expect(config(newState, updateInterval(5))).toEqual({
      ...newState,
      refresh_interval: 5,
    });
  });
});
