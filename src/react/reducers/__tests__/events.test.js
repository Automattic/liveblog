import pollingData from '../../mockData/reducers/polling';
import eventsData from '../../mockData/reducers/events';

import { applyUpdate, eventsApplyUpdate } from '../../utils/utils';

import { initialState, events } from '../events';
import { getEventsSuccess } from '../../actions/eventsActions';
import { pollingSuccess } from '../../actions/apiActions';

describe('events reducer', () => {
  it('should return the initial state', () => {
    expect(events(undefined, {})).toEqual(initialState);
  });

  const stateAfterGetEventsSuccess = {
    ...initialState,
    error: false,
    entries: applyUpdate(initialState.entries, eventsData),
  };

  it('should handle GET_EVENTS_SUCCESS', () => {
    expect(
      events(initialState, getEventsSuccess(eventsData)),
    ).toEqual(stateAfterGetEventsSuccess);
  });

  const stateAfterPollingSuccess = {
    ...stateAfterGetEventsSuccess,
    error: false,
    entries: eventsApplyUpdate(stateAfterGetEventsSuccess.entries, pollingData.entries),
  };

  it('should handle POLLING_SUCCESS', () => {
    expect(
      events(stateAfterPollingSuccess, pollingSuccess(pollingData, true)),
    ).toEqual(stateAfterPollingSuccess);
  });
});
