import moment from './extendedMoment';

/* eslint-disable no-param-reassign */
export const getLastOfObject = object =>
  object[Object.keys(object)[Object.keys(object).length - 1]];

export const getFirstOfObject = object => object[Object.keys(object)[0]];

export const getItemOfObject = (object, key) => object[Object.keys(object)[key]];

/**
 * Sort entries based on their timestamp
 *
 * @param entries
 * @return {*}
 */
export const sortEntriesByTimestamp = (entries) => {
  const sortedEntries = Object.values(entries).sort((a, b) => b.timestamp - a.timestamp);
  const newEntries = {};
  sortedEntries.forEach((entry) => {
    const id = `id_${entry.id}`;
    newEntries[id] = entry;
  });
  return newEntries;
};

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

    // sort entries by timestamp to persist order
    accumulator = sortEntriesByTimestamp(accumulator);

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

    // sort entries by timestamp to persist order
    accumulator = sortEntriesByTimestamp(accumulator);

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
      if (Object.prototype.hasOwnProperty.call(accumulator, id)) {
        accumulator[id] = entry;
      } else {
        accumulator = {
          [id]: entry,
          ...accumulator,
        };
      }
    }

    if (entry.status === 'draft' && Object.prototype.hasOwnProperty.call(accumulator, id)) {
      accumulator[id] = entry;
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

    // sort entries by timestamp to persist order
    accumulator = { ...sortEntriesByTimestamp(accumulator) };

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
  if (window.liveblog_settings.is_admin === '1') {
    return true;
  }
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
 * Returns a formated string indicating how long ago a timestamp was.
 * @param {Number} timestamp Unix Timestamp in seconds
 * @return {String} utcOffset Utc Offset from server
 */
export const timeAgo = timestamp => moment.unix(timestamp).utc().fromNow();

/**
 * Returns a formated string from timestamp in HH MM format.
 * @param {Number} timestamp
 * @return {String} timezoneString time zone from server
 */
export const formattedTime = (timestamp, timezoneString, timeFormat) =>
  moment.unix(timestamp).tz(timezoneString).formatUsingDateTime(timeFormat);

export const getCurrentTimestamp = () => Math.floor(Date.now() / 1000);

export const getPollingPages = (current, next) => {
  if (!next) return current;
  return Math.max(next, 1);
};

/**
 * Fires of any oembed triggers need and adds an event listener that
 * can used to extend oembed support.
 */
export const triggerOembedLoad = (element = false, initalLoad = false) => {
  if (window.instgrm && element && element.querySelector('.instagram-media')) {
    window.instgrm.Embeds.process();
  }

  if (window.twttr && element && element.querySelector('.twitter-tweet')) {
    Array.from(element.querySelectorAll('.twitter-tweet')).forEach((ele) => {
      window.twttr.widgets.load(ele);
    });
  }

  if (window.FB) {
    if (initalLoad) {
      window.FB.XFBML.parse();
    } else if (element) {
      window.FB.XFBML.parse(element);
    }
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

/**
 * Filter to only new entries
 *
 * @param entries
 * @param config
 */
export const filterNewPollingEntries = (entries) => {
  const newEntries = [];

  Object.keys(entries).forEach((key) => {
    if ('new' === entries[key].type) {
      newEntries.push(entries[key]);
    }
  });

  return newEntries;
};
