import {
  getLastOfObject,
  getFirstOfObject,
  getPollingPages,
  getNewestEntry,
} from '../utils';

describe('utils', () => {
  const dummyObj = {
    one: {
      data: 'Test 1',
    },
    two: {
      data: 'Test 2',
    },
    three: {
      data: 'Test 3',
    },
  };

  it('getLastObjectOf should return the last item in an object', () => {
    expect(getLastOfObject(dummyObj)).toEqual({ data: 'Test 3' });
  });

  it('getLastObjectOf should return the last item in an object', () => {
    expect(getFirstOfObject(dummyObj)).toEqual({ data: 'Test 1' });
  });

  it('getPollingPages should return the correct pages number', () => {
    expect(getPollingPages(1, false)).toEqual(1);
    expect(getPollingPages(4, 8)).toEqual(8);
    expect(getPollingPages(1, 0)).toEqual(1);
    expect(getPollingPages(2, -1)).toEqual(1);
  });

  const olderEntry = { timestamp: 1511136000 };
  const newerEntry = { timestamp: 1511568000 };

  it('getNewestEntry should return the newest entry', () => {
    expect(getNewestEntry(olderEntry, newerEntry)).toEqual(newerEntry);
    expect(getNewestEntry(false, false)).toBeFalsy();
    expect(getNewestEntry(false, newerEntry)).toEqual(newerEntry);
    expect(getNewestEntry(olderEntry, false)).toEqual(olderEntry);
    expect(getNewestEntry(newerEntry, olderEntry)).toEqual(newerEntry);
  });
});
