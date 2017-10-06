import { entryListChanged } from '../utils';

describe('entry list changed', () => {
  it('should return true if an item has been added', () => {
    const previous = [];
    const current = [
      { id: '170', updated: '1507303379' },
    ];
    expect(entryListChanged(previous, current)).toBeTruthy();
  });

  it('should return true if an item has been updated', () => {
    const previous = [
      { id: '170', updated: '1507303379' },
    ];
    const current = [
      { id: '170', updated: '1507303380' },
    ];
    expect(entryListChanged(previous, current)).toBeTruthy();
  });

  it('should return true if an item has been deleted', () => {
    const previous = [
      { id: '170', updated: '1507303379' },
    ];
    const current = [];
    expect(entryListChanged(previous, current)).toBeTruthy();
  });

  it('should return false if the entry list is the same', () => {
    const previous = [
      { id: '170', updated: '1507303379' },
    ];
    const current = [
      { id: '170', updated: '1507303379' },
    ];
    expect(entryListChanged(previous, current)).toBeFalsy();
  });
});
