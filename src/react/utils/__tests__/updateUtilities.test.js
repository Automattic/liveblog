import { applyUpdate, eventsApplyUpdate, pollingApplyUpdate } from '../utils';
import {
  currentEntries,
  newEntries,
  expectedEntries,
  expectedEntriesPolling,
} from '../../mockData/utils/applyUpdateEntries';
import { currentEvents, newEvents, expectedEvents } from '../../mockData/utils/eventsApplyUpdateEntries';

describe('update utilities', () => {
  it('applyUpdate should add, remove and update an entry', () => {
    expect(applyUpdate(currentEntries, newEntries)).toEqual(expectedEntries);
  });

  it('eventsApplyUpdate should add, remove and update an event', () => {
    expect(eventsApplyUpdate(currentEvents, newEvents)).toEqual(expectedEvents);
  });

  it('pollingApplyUpdate should add, remove and update an entry if renderNewEntries is true', () => {
    expect(pollingApplyUpdate(currentEntries, newEntries, true)).toEqual(expectedEntries);
  });

  it('pollingApplyUpdate only remove and update an entry if renderNewEntries is false', () => {
    expect(pollingApplyUpdate(currentEntries, newEntries, false)).toEqual(expectedEntriesPolling);
  });
});
