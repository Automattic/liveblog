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

export const daysBetween = (timestamp1, timestamp2) => {
  const day = 1000 * 60 * 60 * 24;
  const difference = Math.abs(timestamp1 - timestamp2);
  return Math.round(difference / day);
};

/**
 * Returns a formated string indicating how long ago a timestamp was.
 * @param {Number} timestamp
 */
export const timeAgo = (timestamp) => {
  const date = new Date(timestamp * 1000);

  // If its greater than 30 days ago.
  if (daysBetween((timestamp * 1000), Date.now()) >= 30) {
    let day = date.getUTCDate();
    let month = date.getUTCMonth() + 1;
    let year = date.getUTCFullYear();

    if (day < 10) day = `0${day}`;
    if (month < 10) month = `0${month}`;
    if (year < 10) year = `0${year}`;

    return `${day}/${month}/${year}`;
  }

  const units = [
    { name: 's', limit: 60, in_seconds: 1 },
    { name: 'm', limit: 3600, in_seconds: 60 },
    { name: 'h', limit: 86400, in_seconds: 3600 },
    { name: 'd', limit: 604800, in_seconds: 86400 },
    { name: 'w', limit: 2629743, in_seconds: 604800 },
    { name: 'm', limit: 31556926, in_seconds: 2629743 },
    { name: 'y', limit: null, in_seconds: 31556926 },
  ];

  let diff = (new Date() - new Date(timestamp * 1000)) / 1000;
  if (diff < 5) return 'now';

  let output;

  for (let i = 0; i < units.length; i += 1) {
    if (diff < units[i].limit || !units[i].limit) {
      diff = Math.floor(diff / units[i].in_seconds);
      output = `${diff}${units[i].name} ago`;
      break;
    }
  }

  return output;
};

export const formattedTime = (timestamp) => {
  const time = new Date(timestamp * 1000);
  const hours = time.getUTCHours() < 10 ? `0${time.getUTCHours()}` : time.getUTCHours();
  const mins = time.getUTCMinutes() < 10 ? `0${time.getUTCMinutes()}` : time.getUTCMinutes();
  return `${hours}:${mins}`;
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

  if (window.FB && element.querySelector('.fb-post')) {
    window.FB.XFBML.parse();
  }

  window.dispatchEvent(new CustomEvent('omembedTrigger'));
};
