/* global jQuery */
/**
 * RxJS ajax pretty much interchangable with axios.
 * Using RxJS ajax as it's already an observable to use with redux-observable.
 */
import { ajax } from 'rxjs/observable/dom/ajax';

import {
  getCurrentTimestamp,
} from '../utils/utils';

import {
  getTinyMCEContent,
  clearTinyMCEContent,
  clearAuthors,
  clearHeadline,
} from '../components/TinyMCEEditor';

const getParams = x => `?${Object.keys(x).map(p => `&${p}=${x[p]}`).join('')}`;

const secureAjax = (settings) => {
  const secureSettings = settings;
  secureSettings.url = secureSettings.url.replace('http://', 'https://');
  return ajax(secureSettings);
};

export function getEntries(page, config, newestEntry) {
  const settings = {
    url: `${config.endpoint_url}get-entries/${page}/${newestEntry.id || config.latest_entry_id}-${newestEntry.timestamp || config.latest_entry_timestamp}`,
    method: 'GET',
    crossDomain: config.cross_domain,
  };

  return secureAjax(settings);
}

export function polling(newestEntryTimestamp, config) {
  let timestamp = getCurrentTimestamp();

  // Round out the timestamp to get a higher cache hitrate.
  // Rather than a random scatter of timestamps,
  // this allows multiple clients to make a request with the same timestamp.
  const refreshInterval = parseInt(config.refresh_interval, 10);
  timestamp = Math.floor(timestamp / refreshInterval) * refreshInterval;

  const settings = {
    url: `${config.endpoint_url}entries/${(newestEntryTimestamp + 1) || 0}/${timestamp}/`,
    method: 'GET',
    crossDomain: config.cross_domain,
  };

  return secureAjax(settings);
}

export function createEntry(entry, config, nonce = false) {
  const settings = {
    url: `${config.endpoint_url}crud/`,
    method: 'POST',
    body: {
      crud_action: 'insert',
      post_id: config.post_id,
      content: (config.usetinymce === '1') ? getTinyMCEContent() : entry.content,
      author_id: entry.author,
      contributor_ids: entry.contributors,
      headline: entry.headline,
    },
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
    crossDomain: config.cross_domain,
  };

  // Clear TinyMCE after a brief delay.
  if (config.usetinymce === '1') {
    setTimeout(() => {
      clearTinyMCEContent();
      clearAuthors();
      clearHeadline();
    }, 250);
  }

  jQuery(document).trigger('liveblog-entry-created', [settings]);

  return secureAjax(settings);
}

export function updateEntry(entry, config, nonce = false) {
  const settings = {
    url: `${config.endpoint_url}crud/`,
    method: 'POST',
    body: {
      crud_action: 'update',
      post_id: config.post_id,
      entry_id: entry.id,
      content: (config.usetinymce === '1') ? getTinyMCEContent() : entry.content,
      author_id: entry.author,
      contributor_ids: entry.contributors,
      headline: entry.headline,
    },
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
    crossDomain: config.cross_domain,
  };

  jQuery(document).trigger('liveblog-entry-updated', [settings]);

  return secureAjax(settings);
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
    crossDomain: config.cross_domain,
  };

  jQuery(document).trigger('liveblog-entry-deleted', [settings]);

  return secureAjax(settings);
}

export function getEvents(config, newestEntry) {
  const settings = {
    url: `${config.endpoint_url}get-key-events/${newestEntry.id || config.latest_entry_id}-${newestEntry.timestamp || config.latest_entry_timestamp}`,
    crossDomain: config.cross_domain,
    method: 'GET',
  };

  return secureAjax(settings);
}

export function jumpToEvent(id, config, newestEntry) {
  const settings = {
    url: `${config.endpoint_url}jump-to-key-event/${id}/${newestEntry.id || 0}-${newestEntry.timestamp || 0}${config.paginationType === 'loadMore' ? '/all' : ''}`,
    crossDomain: config.cross_domain,
    method: 'GET',
  };

  return secureAjax(settings);
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
    crossDomain: config.cross_domain,
  };

  return secureAjax(settings);
}

export function getAuthors(term, config) {
  const settings = {
    url: `${config.autocomplete[3].url}${term}`,
    method: 'GET',
    crossDomain: config.cross_domain,
  };

  return secureAjax(settings);
}

export function getHashtags(term, config) {
  const settings = {
    url: `${config.autocomplete[2].url}${term}`,
    method: 'GET',
    crossDomain: config.cross_domain,
  };

  return secureAjax(settings);
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
    crossDomain: config.cross_domain,
  };

  return secureAjax(settings);
}

export function uploadImage(formData) {
  const location = window.location;

  const settings = {
    url: `${location.protocol}//${location.hostname}/wp-admin/admin-ajax.php`,
    method: 'POST',
    body: formData,
  };

  return secureAjax(settings);
}

export function getMedia(params) {
  const location = window.location;

  const settings = {
    url: `${location.protocol}//${location.hostname}/wp-json/wp/v2/media${getParams(params)}`,
    method: 'GET',
  };

  return secureAjax(settings);
}
