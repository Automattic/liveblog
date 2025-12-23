import moment from './extendedMoment';

/* eslint-disable no-param-reassign */
export const getLastOfObject = object =>
  object[Object.keys(object)[Object.keys(object).length - 1]];

export const getFirstOfObject = object => object[Object.keys(object)[0]];

export const getItemOfObject = (object, key) => object[Object.keys(object)[key]];

/**
 * Apply updated entries to current entries.
 * @param {Object} currentEntries
 * @param {Array} newEntries
 */
export const applyUpdate = (currentEntries, newEntries) =>
  newEntries.reduce((accumulator, entry) => {
    const id = `id_${entry.id}`;

    if (entry.type === 'new') {
      accumulator = {
        ...accumulator,
        [id]: entry,
      };
    }

    if (entry.type === 'update') {
      if (Object.prototype.hasOwnProperty.call(accumulator, id)) {
        accumulator[id] = entry;
      } else {
        accumulator = {
          ...accumulator,
          [id]: entry,
        };
      }
    }

    if (entry.type === 'delete') {
      delete accumulator[id];
    }

    return accumulator;
  }, { ...currentEntries });

/**
 * Apply updated events to current events.
 * @param {Object} currentEntries
 * @param {Array} newEntries
 */
export const eventsApplyUpdate = (currentEntries, newEntries) =>
  newEntries.reduce((accumulator, entry) => {
    const id = `id_${entry.id}`;

    if (entry.type === 'new' && entry.key_event) {
      accumulator = {
        [id]: entry,
        ...accumulator,
      };
    }

    if (Object.prototype.hasOwnProperty.call(accumulator, id)) {
      accumulator[id] = entry;
    }

    if (!entry.key_event || entry.type === 'delete') {
      delete accumulator[id];
    }

    return accumulator;
  }, { ...currentEntries });

/**
 * Apply updates from polling to current entries
 * @param {Object} currentEntries
 * @param {Array} newEntries
 * @param {Boolean} renderNewEntries
 */
export const pollingApplyUpdate = (currentEntries, newEntries, renderNewEntries) =>
  newEntries.reduce((accumulator, entry) => {
    const id = `id_${entry.id}`;

    if (entry.type === 'new' && renderNewEntries) {
      accumulator = {
        [id]: entry,
        ...accumulator,
      };
    }

    if (entry.type === 'update' && Object.prototype.hasOwnProperty.call(accumulator, id)) {
      accumulator[id] = entry;
    }

    if (entry.type === 'delete') {
      delete accumulator[id];
    }

    return accumulator;
  }, { ...currentEntries });

/**
 * Determine whether we should render new entries or prompt the user that a new entry is available.
 * Will return false if the user is not on page one or if the user is on page 1 but the latest
 * entry is not on the screen.
 * @param {Number} page
 * @param {Object} entries
 * @param {Object} polling
 */
export const shouldRenderNewEntries = (page, entries, polling) => {
  if (page !== 1) return false;
  if (Object.keys(polling).length > 0) return false;
  const element = document.getElementById(Object.keys(entries)[0]);
  if (!element) return true;
  return element.getBoundingClientRect().y > 0;
};

/**
 * Determine the newest entry from current and updated entries
 * @param {Object} current
 * @param {Array} updates
 */
export const getNewestEntry = (current, update, entries = false) => {
  if (!current && !update) return false;
  if (!update) return current;
  if (!current && update) return update;
  if (update.type === 'delete' && update.id === current.id && entries) {
    return getItemOfObject(entries, 1);
  }
  if (current.timestamp > update.timestamp) return current;
  return update;
};

/**
 * Get days between two timestamps
 * @param {Object} time Moment Time
 * @param {Number} utcOffset Utc Offset from server
 * @return {Number}
 */
export const daysAgo = (time, utcOffset) => {
  const currentUTCTime = moment().utcOffset(utcOffset, true);
  return currentUTCTime.diff(time, 'days');
};

/**
 * Returns a formatted string indicating how long ago a timestamp was.
 *
 * @param {number} timestamp Unix timestamp in seconds (UTC).
 * @return {string} Human-readable time difference (e.g., "5 minutes ago").
 */
export const timeAgo = timestamp => moment.unix(timestamp).fromNow();

/**
 * Calculate the UTC offset in minutes for a specific timestamp in a given timezone.
 * This accounts for DST by determining the offset at the time of the entry,
 * not the current offset.
 *
 * @param {Number} timestamp Unix timestamp in seconds.
 * @param {String} timezoneString IANA timezone string (e.g., "America/New_York").
 * @return {Number|null} Offset in minutes, or null if calculation fails.
 */
const getTimezoneOffsetForTimestamp = (timestamp, timezoneString) => {
  if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat === 'undefined') {
    return null;
  }

  try {
    const date = new Date(timestamp * 1000);

    // Format the date in the target timezone and UTC to calculate the difference
    const localString = date.toLocaleString('en-US', { timeZone: timezoneString });
    const utcString = date.toLocaleString('en-US', { timeZone: 'UTC' });

    const localDate = new Date(localString);
    const utcDate = new Date(utcString);

    // Return offset in minutes
    return (localDate - utcDate) / 60000;
  } catch (e) {
    // Invalid timezone string or other error
    return null;
  }
};

/**
 * Returns a formatted string from timestamp.
 *
 * @param {Number} timestamp Unix timestamp in seconds.
 * @param {Number} utcOffset UTC offset in minutes (fallback for legacy/missing timezone_string).
 * @param {String} timeFormat PHP date format string.
 * @param {String} timezoneString Optional IANA timezone string for DST-aware formatting.
 * @return {String} Formatted date/time string.
 */
export const formattedTime = (timestamp, utcOffset, timeFormat, timezoneString = null) => {
  let offset = parseInt(utcOffset, 10);

  // If timezone_string is available, calculate the correct offset for this specific timestamp.
  // This ensures DST is handled correctly for entries from different seasons.
  if (timezoneString) {
    const dstAwareOffset = getTimezoneOffsetForTimestamp(timestamp, timezoneString);
    if (dstAwareOffset !== null) {
      offset = dstAwareOffset;
    }
  }

  return moment.unix(timestamp).utcOffset(offset, false).formatUsingDateTime(timeFormat);
};

export const getCurrentTimestamp = () => Math.floor(Date.now() / 1000);

export const getPollingPages = (current, next) => {
  if (!next) return current;
  return Math.max(next, 1);
};

/**
 * Fires of any oembed triggers need and adds an event listener that
 * can used to extend oembed support.
 */
export const triggerOembedLoad = (element) => {
  if (window.instgrm && element.querySelector('.instagram-media')) {
    window.instgrm.Embeds.process();
  }

  if (window.twttr && element.querySelector('.twitter-tweet')) {
    Array.from(element.querySelectorAll('.twitter-tweet')).forEach((ele) => {
      window.twttr.widgets.load(ele);
    });
  }

  // Parse Facebook embeds when SDK is available.
  // Uses element parameter to scope parsing and handles both modern HTML5 format
  // (<div class="fb-post">) and legacy XFBML format (<fb:post>) from older cached embeds.
  if (window.FB) {
    window.FB.XFBML.parse(element);
  }

  window.dispatchEvent(new CustomEvent('omembedTrigger'));
};

/**
 * Get the correct id of which entry to scroll to on pagination
 */
export const getScrollToId = (entries, key) => {
  if (key === 'first') {
    return `id_${entries[0].id}`;
  }

  if (key === 'last') {
    return `id_${entries[entries.length - 1].id}`;
  }

  return `id_${entries[0].id}`;
};
