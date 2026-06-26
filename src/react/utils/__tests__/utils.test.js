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

    const sdkUrls = {
      facebook: 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.5',
      twitter: 'https://platform.twitter.com/widgets.js',
      instagram: 'https://www.instagram.com/embed.js',
      reddit: 'https://embed.reddit.com/widgets.js',
    };

    const injectedScript = name => document.getElementById(`${name}-js`);

    beforeEach(() => {
      // Store original window properties
      originalWindow = {
        FB: window.FB,
        twttr: window.twttr,
        instgrm: window.instgrm,
        liveblog_settings: window.liveblog_settings,
        dispatchEvent: window.dispatchEvent,
      };

      // Provider SDKs are unavailable until explicitly loaded by each test.
      window.FB = undefined;
      window.twttr = undefined;
      window.instgrm = undefined;

      // Expose SDK URLs the way the server-side localisation does.
      window.liveblog_settings = { embed_sdks: sdkUrls };

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
      window.liveblog_settings = originalWindow.liveblog_settings;
      window.dispatchEvent = originalWindow.dispatchEvent;

      // Remove any SDK scripts injected during a test so state does not leak.
      ['facebook', 'twitter', 'instagram', 'reddit'].forEach((name) => {
        const script = injectedScript(name);
        if (script) script.remove();
      });
    });

    it('should call FB.XFBML.parse with the element when the SDK is loaded and markup is present', () => {
      const mockParse = jest.fn();
      window.FB = {
        XFBML: {
          parse: mockParse,
        },
      };

      mockElement.innerHTML = '<div class="fb-post" data-href="https://facebook.com/test"></div>';

      triggerOembedLoad(mockElement);

      expect(mockParse).toHaveBeenCalledTimes(1);
      expect(mockParse).toHaveBeenCalledWith(mockElement);
    });

    it('should not process any provider when no embed markup is present', () => {
      const mockParse = jest.fn();
      window.FB = { XFBML: { parse: mockParse } };
      window.twttr = { widgets: { load: jest.fn() } };
      window.instgrm = { Embeds: { process: jest.fn() } };

      triggerOembedLoad(mockElement);

      expect(mockParse).not.toHaveBeenCalled();
      expect(window.twttr.widgets.load).not.toHaveBeenCalled();
      expect(window.instgrm.Embeds.process).not.toHaveBeenCalled();
    });

    it('should not throw when an SDK is not available', () => {
      window.FB = undefined;
      mockElement.innerHTML = '<div class="fb-post"></div>';

      expect(() => triggerOembedLoad(mockElement)).not.toThrow();
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

    it('should inject a provider SDK on demand when markup is present but the SDK is not loaded', () => {
      mockElement.innerHTML = '<blockquote class="twitter-tweet"></blockquote>';

      expect(injectedScript('twitter')).toBeNull();

      triggerOembedLoad(mockElement);

      const script = injectedScript('twitter');
      expect(script).not.toBeNull();
      expect(script.src).toBe(sdkUrls.twitter);
      expect(script.async).toBe(true);
    });

    it('should not inject a provider SDK when its markup is absent', () => {
      mockElement.innerHTML = '<blockquote class="twitter-tweet"></blockquote>';

      triggerOembedLoad(mockElement);

      // Only Twitter markup is present, so other SDKs must not be injected.
      expect(injectedScript('facebook')).toBeNull();
      expect(injectedScript('instagram')).toBeNull();
      expect(injectedScript('reddit')).toBeNull();
      expect(injectedScript('twitter')).not.toBeNull();
    });

    it('should inject each SDK only once across multiple entries', () => {
      mockElement.innerHTML = '<blockquote class="twitter-tweet"></blockquote>';
      triggerOembedLoad(mockElement);

      const anotherEntry = document.createElement('div');
      anotherEntry.innerHTML = '<blockquote class="twitter-tweet"></blockquote>';
      triggerOembedLoad(anotherEntry);

      expect(document.querySelectorAll('#twitter-js').length).toBe(1);
    });

    it('should inject the Reddit SDK on demand for Reddit embeds', () => {
      mockElement.innerHTML = '<blockquote class="reddit-embed"></blockquote>';

      triggerOembedLoad(mockElement);

      expect(injectedScript('reddit')).not.toBeNull();
    });

    it('should not inject any SDK when no SDK URLs are configured', () => {
      window.liveblog_settings = { embed_sdks: {} };
      mockElement.innerHTML = '<blockquote class="twitter-tweet"></blockquote>';

      triggerOembedLoad(mockElement);

      expect(injectedScript('twitter')).toBeNull();
    });
  });
});
