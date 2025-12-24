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
 * Returns a formatted string indicating how long ago a timestamp was.
 * Uses Intl.RelativeTimeFormat for locale-aware formatting.
 *
 * @param {number} timestamp Unix timestamp in seconds (UTC).
 * @param {string} locale WordPress locale (e.g., "de_DE"). Defaults to "en_US".
 * @return {string} Human-readable time difference (e.g., "5 minutes ago", "vor 5 Minuten").
 */
export const timeAgo = (timestamp, locale = 'en_US') => {
  const seconds = Math.floor(Date.now() / 1000) - timestamp;

  // Convert WordPress locale format (de_DE) to BCP 47 format (de-DE)
  const bcp47Locale = locale.replace('_', '-');

  const rtf = new Intl.RelativeTimeFormat(bcp47Locale, { numeric: 'auto' });

  // Determine the appropriate unit and value
  const absSeconds = Math.abs(seconds);
  if (absSeconds < 60) {
    return rtf.format(-seconds, 'second');
  }
  if (absSeconds < 3600) {
    return rtf.format(-Math.round(seconds / 60), 'minute');
  }
  if (absSeconds < 86400) {
    return rtf.format(-Math.round(seconds / 3600), 'hour');
  }
  if (absSeconds < 2592000) { // 30 days
    return rtf.format(-Math.round(seconds / 86400), 'day');
  }
  if (absSeconds < 31536000) { // 365 days
    return rtf.format(-Math.round(seconds / 2592000), 'month');
  }
  return rtf.format(-Math.round(seconds / 31536000), 'year');
};

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
 * Get ordinal suffix for a day number.
 * @param {number} day - Day of month (1-31).
 * @return {string} Ordinal suffix (st, nd, rd, th).
 */
const getOrdinalSuffix = (day) => {
  if (day >= 11 && day <= 13) return 'th';
  switch (day % 10) {
    case 1: return 'st';
    case 2: return 'nd';
    case 3: return 'rd';
    default: return 'th';
  }
};

/**
 * Format a date using PHP date format string.
 * Uses UTC methods on a pre-adjusted Date object.
 *
 * @param {Date} date - Date object (pre-adjusted for target timezone).
 * @param {string} format - PHP date format string.
 * @return {string} Formatted date string.
 */
const formatPhpDate = (date, format) => {
  const pad = (num, len = 2) => String(num).padStart(len, '0');

  // PHP format token handlers (using UTC methods since date is pre-adjusted)
  const tokens = {
    // Day
    d: () => pad(date.getUTCDate()),
    D: () => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][date.getUTCDay()],
    j: () => date.getUTCDate(),
    l: () => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getUTCDay()],
    N: () => date.getUTCDay() || 7,
    S: () => getOrdinalSuffix(date.getUTCDate()),
    w: () => date.getUTCDay(),
    // Month
    F: () => ['January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'][date.getUTCMonth()],
    m: () => pad(date.getUTCMonth() + 1),
    M: () => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][date.getUTCMonth()],
    n: () => date.getUTCMonth() + 1,
    t: () => new Date(date.getUTCFullYear(), date.getUTCMonth() + 1, 0).getDate(),
    // Year
    Y: () => date.getUTCFullYear(),
    y: () => String(date.getUTCFullYear()).slice(-2),
    // Time
    a: () => (date.getUTCHours() < 12 ? 'am' : 'pm'),
    A: () => (date.getUTCHours() < 12 ? 'AM' : 'PM'),
    g: () => date.getUTCHours() % 12 || 12,
    G: () => date.getUTCHours(),
    h: () => pad(date.getUTCHours() % 12 || 12),
    H: () => pad(date.getUTCHours()),
    i: () => pad(date.getUTCMinutes()),
    s: () => pad(date.getUTCSeconds()),
    // Timezone
    O: () => {
      const offset = -date.getTimezoneOffset();
      const sign = offset >= 0 ? '+' : '-';
      return `${sign}${pad(Math.floor(Math.abs(offset) / 60))}${pad(Math.abs(offset) % 60)}`;
    },
    P: () => {
      const offset = -date.getTimezoneOffset();
      const sign = offset >= 0 ? '+' : '-';
      return `${sign}${pad(Math.floor(Math.abs(offset) / 60))}:${pad(Math.abs(offset) % 60)}`;
    },
    // Full formats
    c: () => date.toISOString(),
    U: () => Math.floor(date.getTime() / 1000),
  };

  let result = '';
  let escaped = false;

  for (let i = 0; i < format.length; i++) {
    const char = format[i];

    if (char === '\\' && !escaped) {
      escaped = true;
      continue;
    }

    if (escaped) {
      result += char;
      escaped = false;
      continue;
    }

    if (tokens[char]) {
      result += tokens[char]();
    } else {
      result += char;
    }
  }

  return result;
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

  // Create a Date adjusted to the target timezone by adding the offset
  // We use UTC methods in formatPhpDate to avoid local timezone interference
  const adjustedMs = (timestamp * 1000) + (offset * 60 * 1000);
  const adjustedDate = new Date(adjustedMs);

  return formatPhpDate(adjustedDate, timeFormat);
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
