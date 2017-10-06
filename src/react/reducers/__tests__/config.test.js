import { initialState, config } from '../config';
import types from '../../actions/actionTypes';
import * as actions from '../../actions/configActions';

describe('config reducer', () => {
  it('should return the initial state', () => {
    expect(
      config(undefined, {}),
    ).toEqual(initialState);
  });

  it(`should handle ${types.LOAD_CONFIG}`, () => {
    const exampleConfig = {
      api: 'http://liveblog-v2.app/wp-json/liveblog/v2',
      can_edit: 'true',
      last_entry: {
        id: '166',
        updated: '1507299023',
      },
      nonce: 'fd82cf52dc',
      post_id: '1',
      timestamp: '1507299934',
    };

    expect(
      config(undefined, actions.loadConfig(exampleConfig)),
    ).toEqual(exampleConfig);
  });
});
