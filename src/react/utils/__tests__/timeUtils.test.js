import { formattedTime, timeAgo, getCurrentTimestamp } from '../utils';

describe('time utils', () => {
  it('getCurrentTimestamp should return the current timestamp', () => {
    expect(getCurrentTimestamp()).toEqual(Math.floor(Date.now() / 1000));
  });
});
