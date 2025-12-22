import { polling } from '../api';

// Mock rxjs/ajax
jest.mock('rxjs/ajax', () => ({
  ajax: jest.fn((settings) => settings),
}));

// Mock getCurrentTimestamp
jest.mock('../../utils/utils', () => ({
  getCurrentTimestamp: jest.fn(),
}));

import { getCurrentTimestamp } from '../../utils/utils';

describe('api service', () => {
  describe('polling', () => {
    const baseConfig = {
      endpoint_url: 'https://example.com/wp-json/liveblog/v1/123/',
      refresh_interval: '10', // 10 seconds (matches PHP REFRESH_INTERVAL constant)
      cross_domain: false,
    };

    beforeEach(() => {
      jest.clearAllMocks();
    });

    it('should round both start and end timestamps to bucket boundaries', () => {
      // Current time: 1734567895 (not on a 10-second boundary)
      getCurrentTimestamp.mockReturnValue(1734567895);

      // Newest entry timestamp: 1734567883 (not on a 10-second boundary)
      const newestEntryTimestamp = 1734567883;

      const result = polling(newestEntryTimestamp, baseConfig);

      // refresh_interval is 10 seconds
      // Start: (1734567883 + 1) / 10 = 173456788.4 -> floor -> 173456788 * 10 = 1734567880
      // End: 1734567895 / 10 = 173456789.5 -> floor -> 173456789 * 10 = 1734567890
      expect(result.url).toBe('https://example.com/wp-json/liveblog/v1/123/entries/1734567880/1734567890/');
    });

    it('should produce identical URLs for clients with timestamps in the same bucket', () => {
      getCurrentTimestamp.mockReturnValue(1734567895);

      // All clients have newestEntryTimestamp in the same 10-second bucket (1734567880-1734567889)
      // After +1 and floor division by 10, they all map to bucket 1734567880
      // Client A: (1734567880 + 1) / 10 = 173456788.1 -> 173456788 * 10 = 1734567880
      const resultA = polling(1734567880, baseConfig);

      // Client B: (1734567885 + 1) / 10 = 173456788.6 -> 173456788 * 10 = 1734567880
      const resultB = polling(1734567885, baseConfig);

      // Client C: (1734567888 + 1) / 10 = 173456788.9 -> 173456788 * 10 = 1734567880
      const resultC = polling(1734567888, baseConfig);

      // All should produce the same URL (same 10-second bucket)
      expect(resultA.url).toBe(resultB.url);
      expect(resultB.url).toBe(resultC.url);
      expect(resultA.url).toBe('https://example.com/wp-json/liveblog/v1/123/entries/1734567880/1734567890/');
    });

    it('should handle zero newestEntryTimestamp', () => {
      getCurrentTimestamp.mockReturnValue(1734567890);

      const result = polling(0, baseConfig);

      // Start: (0 + 1) / 10 = 0.1 -> floor -> 0 * 10 = 0
      // End: 1734567890 / 10 = 173456789 * 10 = 1734567890
      expect(result.url).toBe('https://example.com/wp-json/liveblog/v1/123/entries/0/1734567890/');
    });

    it('should handle different refresh intervals', () => {
      getCurrentTimestamp.mockReturnValue(1734567895);

      const config = { ...baseConfig, refresh_interval: '30' }; // 30 seconds
      const result = polling(1734567883, config);

      // Start: (1734567883 + 1) / 30 = 57818929.47 -> floor -> 57818929 * 30 = 1734567870
      // End: 1734567895 / 30 = 57818929.83 -> floor -> 57818929 * 30 = 1734567870
      expect(result.url).toBe('https://example.com/wp-json/liveblog/v1/123/entries/1734567870/1734567870/');
    });
  });
});
