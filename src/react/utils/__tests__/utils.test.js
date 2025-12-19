import {
  getLastOfObject,
  getFirstOfObject,
  getPollingPages,
  getNewestEntry,
  triggerOembedLoad,
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

  describe('triggerOembedLoad', () => {
    let mockElement;
    let originalWindow;

    beforeEach(() => {
      // Store original window properties
      originalWindow = {
        FB: window.FB,
        twttr: window.twttr,
        instgrm: window.instgrm,
        dispatchEvent: window.dispatchEvent,
      };

      // Create a mock DOM element
      mockElement = document.createElement('div');

      // Mock dispatchEvent
      window.dispatchEvent = jest.fn();
    });

    afterEach(() => {
      // Restore original window properties
      window.FB = originalWindow.FB;
      window.twttr = originalWindow.twttr;
      window.instgrm = originalWindow.instgrm;
      window.dispatchEvent = originalWindow.dispatchEvent;
    });

    it('should call FB.XFBML.parse with element when Facebook SDK is available', () => {
      const mockParse = jest.fn();
      window.FB = {
        XFBML: {
          parse: mockParse,
        },
      };

      triggerOembedLoad(mockElement);

      expect(mockParse).toHaveBeenCalledTimes(1);
      expect(mockParse).toHaveBeenCalledWith(mockElement);
    });

    it('should not throw when Facebook SDK is not available', () => {
      window.FB = undefined;

      expect(() => triggerOembedLoad(mockElement)).not.toThrow();
    });

    it('should handle elements with fb-post class (modern HTML5 format)', () => {
      const mockParse = jest.fn();
      window.FB = {
        XFBML: {
          parse: mockParse,
        },
      };

      // Add a modern HTML5 Facebook embed
      mockElement.innerHTML = '<div class="fb-post" data-href="https://facebook.com/test"></div>';

      triggerOembedLoad(mockElement);

      expect(mockParse).toHaveBeenCalledWith(mockElement);
    });

    it('should handle elements with fb:post (legacy XFBML format)', () => {
      const mockParse = jest.fn();
      window.FB = {
        XFBML: {
          parse: mockParse,
        },
      };

      // Add a legacy XFBML Facebook embed
      mockElement.innerHTML = '<fb:post href="https://facebook.com/test" data-width="552"></fb:post>';

      triggerOembedLoad(mockElement);

      // Should still call parse - the SDK handles both formats
      expect(mockParse).toHaveBeenCalledWith(mockElement);
    });

    it('should dispatch omembedTrigger custom event', () => {
      triggerOembedLoad(mockElement);

      expect(window.dispatchEvent).toHaveBeenCalledTimes(1);
      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.any(CustomEvent)
      );
    });

    it('should call Twitter widgets.load when Twitter SDK is available', () => {
      const mockLoad = jest.fn();
      window.twttr = {
        widgets: {
          load: mockLoad,
        },
      };

      // Add a Twitter embed
      mockElement.innerHTML = '<blockquote class="twitter-tweet"></blockquote>';

      triggerOembedLoad(mockElement);

      expect(mockLoad).toHaveBeenCalled();
    });

    it('should call Instagram Embeds.process when Instagram SDK is available', () => {
      const mockProcess = jest.fn();
      window.instgrm = {
        Embeds: {
          process: mockProcess,
        },
      };

      // Add an Instagram embed
      mockElement.innerHTML = '<blockquote class="instagram-media"></blockquote>';

      triggerOembedLoad(mockElement);

      expect(mockProcess).toHaveBeenCalledTimes(1);
    });
  });
});
