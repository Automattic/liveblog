import { initialState, api } from '../api';
import types from '../../actions/actionTypes';
import * as actions from '../../actions/apiActions';

describe('api reducer', () => {
  it('should return the initial state', () => {
    expect(
      api(undefined, {}),
    ).toEqual(initialState);
  });

  it(`should handle ${types.GET_ENTRIES}`, () => {
    const action = actions.getEntries({
      id: '166',
      updated: '1507299023',
    });

    expect(
      api(initialState, action),
    ).toEqual(initialState);
  });

  it(`should handle ${types.GET_ENTRIES_SUCCESS}`, () => {
    const entries = [{
      id: 1,
      postId: 1,
    }, {
      id: 1,
      postId: 1,
    }];

    const action = actions.getEntriesSuccess(entries);

    expect(
      api(initialState, action),
    ).toEqual({
      ...initialState,
      error: false,
      entries,
    });
  });

  it(`should handle ${types.GET_ENTRIES_FAILED}`, () => {
    const action = actions.getEntriesFailed();

    expect(
      api(initialState, action),
    ).toEqual({
      ...initialState,
      error: true,
    });
  });

  it(`should handle ${types.START_POLLING}`, () => {
    const action = actions.startPolling(1507305386);

    expect(
      api(initialState, action),
    ).toEqual({
      ...initialState,
      timestamp: parseInt(action.payload, 10),
    });
  });
});
