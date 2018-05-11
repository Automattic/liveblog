import {
  applyUpdate,
  pollingApplyUpdate,
  getNewestEntry,
} from '../../utils/utils';

import apiData from '../../mockData/reducers/api';
import pollingData from '../../mockData/reducers/polling';
import { initialState, api } from '../api';
import {
  getEntries,
  getEntriesSuccess,
  getEntriesFailed,
  pollingSuccess,
} from '../../actions/apiActions';

describe('api reducer', () => {
  it('should return the initial state', () => {
    expect(api(undefined, {})).toEqual(initialState);
  });

  const stateAfterGetEntries = {
    ...initialState,
    error: false,
    loading: true,
  };

  it('should handle GET_ENTRIES', () => {
    expect(api(initialState, getEntries())).toEqual(stateAfterGetEntries);
  });

  it('should handle GET_ENTRIES_FAILED', () => {
    expect(api(stateAfterGetEntries, getEntriesFailed())).toEqual({
      ...stateAfterGetEntries,
      loading: false,
      error: true,
    });
  });

  const stateAfterGetEntriesSuccess = {
    ...stateAfterGetEntries,
    error: false,
    loading: false,
    entries: applyUpdate({}, apiData.entries),
    newestEntry: getNewestEntry(
      stateAfterGetEntries.newestEntry,
      apiData.entries[0],
    ),
  };

  it('should handle GET_ENTRIES_SUCCESS', () => {
    expect(
      api(stateAfterGetEntries, getEntriesSuccess(apiData, true)),
    ).toEqual(stateAfterGetEntriesSuccess);
  });

  const shouldRenderNewEntries = true;

  const stateAfterPollingSuccess = {
    ...stateAfterGetEntriesSuccess,
    error: false,
    entries: pollingApplyUpdate(
      stateAfterGetEntriesSuccess.entries,
      pollingData.entries,
      shouldRenderNewEntries,
    ),
    newestEntry: shouldRenderNewEntries
      ? getNewestEntry(stateAfterGetEntriesSuccess.newestEntry, pollingData.entries[0])
      : stateAfterGetEntriesSuccess.newestEntry,
  };

  it('should handle POLLING_SUCCESS', () => {
    expect(
      api(stateAfterGetEntriesSuccess, pollingSuccess(pollingData, shouldRenderNewEntries)),
    ).toEqual(stateAfterPollingSuccess);
  });
});
