/**
 * RxJS ajax pretty much interchangable with axios.
 * Using RxJS ajax as it's already an observable to use with redux-observable.
 */
import { ajax } from 'rxjs/observable/dom/ajax';

import {
  getCurrentTimestamp,
} from '../utils/utils';

const getParams = x => `?${Object.keys(x).map(p => `&${p}=${x[p]}`).join('')}`;

export function getEntries(page, config, newestEntry) {
  const settings = {
    url: `${config.endpoint_url}get-entries/${page}/${newestEntry.id || config.latest_entry_id}-${newestEntry.timestamp || config.latest_entry_timestamp}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function polling(newestEntryTimestamp, config) {
  const timestamp = getCurrentTimestamp() + config.timeDifference;

  const settings = {
    url: `${config.endpoint_url}entries/${(newestEntryTimestamp + 1) || 0}/${timestamp}/`,
    method: 'GET',
  };

  return ajax(settings);
}

export function createEntry(entry, config, nonce = false) {
  const settings = {
    url: `${config.endpoint_url}crud/`,
    method: 'POST',
    body: {
      crud_action: 'insert',
      post_id: config.post_id,
      content: entry.content,
      author_id: entry.author,
      contributor_ids: entry.contributors,
    },
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}

export function updateEntry(entry, config, nonce = false) {
  const settings = {
    url: `${config.endpoint_url}crud/`,
    method: 'POST',
    body: {
      crud_action: 'update',
      post_id: config.post_id,
      entry_id: entry.id,
      content: entry.content,
      author_id: entry.author,
      contributor_ids: entry.contributors,
    },
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}

export function deleteEntry(id, config, nonce = false) {
  const settings = {
    url: `${config.endpoint_url}crud/`,
    method: 'POST',
    body: {
      crud_action: 'delete',
      post_id: config.post_id,
      entry_id: id,
    },
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}

export function getEvents(config, newestEntry) {
  const settings = {
    url: `${config.endpoint_url}get-key-events/${newestEntry.id || config.latest_entry_id}-${newestEntry.timestamp || config.latest_entry_timestamp}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function jumpToEvent(id, config, newestEntry) {
  const settings = {
    url: `${config.endpoint_url}jump-to-key-event/${id}/${newestEntry.id || 0}-${newestEntry.timestamp || 0}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function deleteEvent(entry, config, nonce = false) {
  const settings = {
    url: `${config.endpoint_url}crud/`,
    method: 'POST',
    body: {
      crud_action: 'delete_key',
      post_id: config.post_id,
      entry_id: entry.id,
      content: entry.content,
    },
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}

export function getAuthors(term, config) {
  const settings = {
    url: `${config.autocomplete[3].url}${term}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function getHashtags(term, config) {
  const settings = {
    url: `${config.autocomplete[2].url}${term}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function getPreview(content, config) {
  const settings = {
    url: `${config.endpoint_url}preview`,
    method: 'POST',
    body: {
      entry_content: content,
    },
    headers: {
      'Content-Type': 'application/json',
    },
  };

  return ajax(settings);
}

export function uploadImage(formData) {
  const location = window.location;

  const settings = {
    url: `${location.protocol}//${location.hostname}/wp-admin/admin-ajax.php`,
    method: 'POST',
    body: formData,
  };

  return ajax(settings);
}

export function getMedia(params) {
  const location = window.location;

  const settings = {
    url: `${location.protocol}//${location.hostname}/wp-json/wp/v2/media${getParams(params)}`,
    method: 'GET',
  };

  return ajax(settings);
}
