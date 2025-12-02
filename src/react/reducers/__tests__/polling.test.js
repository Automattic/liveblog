import pollingData from '../../mockData/reducers/polling';
import apiData from '../../mockData/reducers/api';
import {
  applyUpdate,
  getNewestEntry,
} from '../../utils/utils';
import { initialState, polling } from '../polling';
import {
  getEntriesSuccess,
  pollingSuccess,
} from '../../actions/apiActions';

describe('polling reducer', () => {
  it('should return the initial state', () => {
    expect(polling(undefined, {})).toEqual(initialState);
  });

  let shouldRenderNewEntries = true;

  const stateAfterGetEntriesSuccess = {
    ...initialState,
    newestEntry: getNewestEntry(
      initialState.newestEntry,
      apiData.entries[0],
    ),
    entries: shouldRenderNewEntries
      ? {}
      : initialState.newestEntry,
  };

  it('should handle GET_ENTRIES_SUCCESS', () => {
    expect(
      polling(initialState, getEntriesSuccess(apiData)),
    ).toEqual(stateAfterGetEntriesSuccess);
  });

  shouldRenderNewEntries = false;

  const stateAfterPollingSuccess = {
    ...stateAfterGetEntriesSuccess,
    error: false,
    entries: shouldRenderNewEntries
      ? {}
      : applyUpdate(
        stateAfterGetEntriesSuccess.entries,
        pollingData.entries.filter(entry => entry.type === 'new'),
      ),
    newestEntry: getNewestEntry(stateAfterGetEntriesSuccess.newestEntry, pollingData.entries[0]),
  };

  it('should handle POLLING_SUCCESS', () => {
    expect(
      polling(stateAfterGetEntriesSuccess, pollingSuccess(pollingData, shouldRenderNewEntries)),
    ).toEqual(stateAfterPollingSuccess);
  });
});
