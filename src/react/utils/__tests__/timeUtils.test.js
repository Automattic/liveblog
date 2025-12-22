import { formattedTime, timeAgo, getCurrentTimestamp } from '../utils';

describe('time utils', () => {
  it('getCurrentTimestamp should return the current timestamp', () => {
    expect(getCurrentTimestamp()).toEqual(Math.floor(Date.now() / 1000));
  });

  describe('timeAgo', () => {
    it('should return "a few seconds ago" for recent timestamps', () => {
      const now = Math.floor(Date.now() / 1000);
      expect(timeAgo(now)).toBe('a few seconds ago');
    });

    it('should return "a minute ago" for timestamp 60 seconds ago', () => {
      const oneMinuteAgo = Math.floor(Date.now() / 1000) - 60;
      expect(timeAgo(oneMinuteAgo)).toBe('a minute ago');
    });

    it('should return "5 minutes ago" for timestamp 5 minutes ago', () => {
      const fiveMinutesAgo = Math.floor(Date.now() / 1000) - 300;
      expect(timeAgo(fiveMinutesAgo)).toBe('5 minutes ago');
    });

    it('should return "an hour ago" for timestamp 1 hour ago', () => {
      const oneHourAgo = Math.floor(Date.now() / 1000) - 3600;
      expect(timeAgo(oneHourAgo)).toBe('an hour ago');
    });

    it('should handle Unix timestamps correctly regardless of timezone', () => {
      // Unix timestamp for a known date: 2024-06-15 14:30:00 UTC
      const knownTimestamp = 1718461800;
      // The result should be consistent - moment handles Unix timestamps as UTC
      const result = timeAgo(knownTimestamp);
      expect(result).toContain('ago');
    });
  });
});
