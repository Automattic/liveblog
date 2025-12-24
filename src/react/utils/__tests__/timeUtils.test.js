import { formattedTime, timeAgo, getCurrentTimestamp } from '../utils';

describe('time utils', () => {
  it('getCurrentTimestamp should return the current timestamp', () => {
    expect(getCurrentTimestamp()).toEqual(Math.floor(Date.now() / 1000));
  });

  describe('timeAgo', () => {
    it('should return relative time for recent timestamps', () => {
      const now = Math.floor(Date.now() / 1000);
      const result = timeAgo(now);
      // Intl.RelativeTimeFormat with numeric: 'auto' returns "now" or "0 seconds ago"
      expect(result).toMatch(/now|0 seconds ago|second/);
    });

    it('should return "1 minute ago" for timestamp 60 seconds ago', () => {
      const oneMinuteAgo = Math.floor(Date.now() / 1000) - 60;
      expect(timeAgo(oneMinuteAgo)).toBe('1 minute ago');
    });

    it('should return "5 minutes ago" for timestamp 5 minutes ago', () => {
      const fiveMinutesAgo = Math.floor(Date.now() / 1000) - 300;
      expect(timeAgo(fiveMinutesAgo)).toBe('5 minutes ago');
    });

    it('should return "1 hour ago" for timestamp 1 hour ago', () => {
      const oneHourAgo = Math.floor(Date.now() / 1000) - 3600;
      expect(timeAgo(oneHourAgo)).toBe('1 hour ago');
    });

    it('should handle Unix timestamps correctly regardless of timezone', () => {
      // Unix timestamp for a known date: 2024-06-15 14:30:00 UTC
      const knownTimestamp = 1718461800;
      const result = timeAgo(knownTimestamp);
      expect(result).toMatch(/ago|year|month/);
    });

    it('should format in German when de_DE locale is passed', () => {
      const fiveMinutesAgo = Math.floor(Date.now() / 1000) - 300;
      const result = timeAgo(fiveMinutesAgo, 'de_DE');
      // German: "vor 5 Minuten"
      expect(result).toBe('vor 5 Minuten');
    });

    it('should format in French when fr_FR locale is passed', () => {
      const fiveMinutesAgo = Math.floor(Date.now() / 1000) - 300;
      const result = timeAgo(fiveMinutesAgo, 'fr_FR');
      // French: "il y a 5 minutes"
      expect(result).toBe('il y a 5 minutes');
    });

    it('should handle hours in different locales', () => {
      const twoHoursAgo = Math.floor(Date.now() / 1000) - 7200;
      expect(timeAgo(twoHoursAgo, 'en_US')).toBe('2 hours ago');
      expect(timeAgo(twoHoursAgo, 'de_DE')).toBe('vor 2 Stunden');
      expect(timeAgo(twoHoursAgo, 'es_ES')).toBe('hace 2 horas');
    });

    it('should handle days in different locales', () => {
      const threeDaysAgo = Math.floor(Date.now() / 1000) - (3 * 86400);
      expect(timeAgo(threeDaysAgo, 'en_US')).toBe('3 days ago');
      expect(timeAgo(threeDaysAgo, 'de_DE')).toBe('vor 3 Tagen');
    });

    it('should default to en_US when no locale is provided', () => {
      const fiveMinutesAgo = Math.floor(Date.now() / 1000) - 300;
      expect(timeAgo(fiveMinutesAgo)).toBe('5 minutes ago');
    });

    it('should convert WordPress locale format to BCP 47', () => {
      // WordPress uses underscores (de_DE), Intl uses hyphens (de-DE)
      const oneHourAgo = Math.floor(Date.now() / 1000) - 3600;
      // This should work with underscore format
      expect(timeAgo(oneHourAgo, 'de_DE')).toBe('vor 1 Stunde');
    });
  });

  describe('formattedTime', () => {
    // Using 24-hour format (H:i) for simpler assertions
    const timeFormat = 'H:i';

    it('should format time using UTC offset when no timezone string provided', () => {
      // Timestamp for 2024-01-15 12:30:00 UTC
      const timestamp = 1705321800;
      const utcOffset = -300; // UTC-5 (EST)

      const result = formattedTime(timestamp, utcOffset, timeFormat);
      // 12:30 UTC - 5 hours = 07:30
      expect(result).toBe('07:30');
    });

    it('should format time using timezone string for DST-aware formatting', () => {
      // Timestamp for 2024-01-15 12:30:00 UTC (winter - EST is UTC-5)
      const winterTimestamp = 1705321800;
      const utcOffset = -240; // Wrong offset (EDT, summer time)
      const timezoneString = 'America/New_York';

      const result = formattedTime(winterTimestamp, utcOffset, timeFormat, timezoneString);
      // Even though utcOffset is wrong (-4), timezone string should give correct result
      // 12:30 UTC - 5 hours (EST in winter) = 07:30
      expect(result).toBe('07:30');
    });

    it('should handle DST transition correctly - winter entry in EST', () => {
      // Timestamp for 2024-01-15 17:00:00 UTC (winter)
      const winterTimestamp = 1705338000;
      const timezoneString = 'America/New_York';
      // UTC offset doesn't matter when timezone string is provided
      const utcOffset = 0;

      const result = formattedTime(winterTimestamp, utcOffset, timeFormat, timezoneString);
      // 17:00 UTC - 5 hours (EST) = 12:00
      expect(result).toBe('12:00');
    });

    it('should handle DST transition correctly - summer entry in EDT', () => {
      // Timestamp for 2024-07-15 17:00:00 UTC (summer)
      const summerTimestamp = 1721062800;
      const timezoneString = 'America/New_York';
      // UTC offset doesn't matter when timezone string is provided
      const utcOffset = 0;

      const result = formattedTime(summerTimestamp, utcOffset, timeFormat, timezoneString);
      // 17:00 UTC - 4 hours (EDT) = 13:00
      expect(result).toBe('13:00');
    });

    it('should fall back to UTC offset for invalid timezone string', () => {
      const timestamp = 1705321800;
      const utcOffset = -300; // UTC-5
      const invalidTimezoneString = 'Invalid/Timezone';

      const result = formattedTime(timestamp, utcOffset, timeFormat, invalidTimezoneString);
      // Should fall back to UTC offset: 12:30 UTC - 5 hours = 07:30
      expect(result).toBe('07:30');
    });

    it('should fall back to UTC offset when timezone string is null', () => {
      const timestamp = 1705321800;
      const utcOffset = -300; // UTC-5

      const result = formattedTime(timestamp, utcOffset, timeFormat, null);
      expect(result).toBe('07:30');
    });

    it('should handle different PHP date formats', () => {
      // Timestamp for 2024-07-15 14:30:00 UTC
      const timestamp = 1721053800;
      const timezoneString = 'America/New_York';
      const utcOffset = 0;

      // 14:30 UTC - 4 hours (EDT) = 10:30 AM
      expect(formattedTime(timestamp, utcOffset, 'g:i A', timezoneString)).toBe('10:30 AM');
      expect(formattedTime(timestamp, utcOffset, 'H:i', timezoneString)).toBe('10:30');
    });

    it('should handle European timezone with DST', () => {
      // Timestamp for 2024-01-15 12:00:00 UTC (winter - CET is UTC+1)
      const winterTimestamp = 1705320000;
      const timezoneString = 'Europe/Paris';
      const utcOffset = 0;

      const result = formattedTime(winterTimestamp, utcOffset, timeFormat, timezoneString);
      // 12:00 UTC + 1 hour (CET) = 13:00
      expect(result).toBe('13:00');

      // Timestamp for 2024-07-15 12:00:00 UTC (summer - CEST is UTC+2)
      const summerTimestamp = 1721044800;
      const summerResult = formattedTime(summerTimestamp, utcOffset, timeFormat, timezoneString);
      // 12:00 UTC + 2 hours (CEST) = 14:00
      expect(summerResult).toBe('14:00');
    });
  });
});
