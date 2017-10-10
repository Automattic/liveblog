/**
 * RxJS ajax pretty much interchangable with axios.
 * Using RxJS ajax as it's already an observable to use with redux-observable.
 */
import { ajax } from 'rxjs/observable/dom/ajax';

export function getEntries(oldestEntryTimestamp, config) {
  const settings = {
    url: `${config.endpoint_url}lazyload/${oldestEntryTimestamp}/0/`,
    method: 'GET',
  };

  return ajax(settings);
}

export function startPolling(newestEntryTimestamp, timestamp, config) {
  const settings = {
    url: `${config.endpoint_url}entries/${newestEntryTimestamp+1}/${timestamp}/`,
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
      content: entry.content
    },
    headers: {
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
      content: entry.content
    },
    headers: {
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
      entry_id: id
    },
    headers: {
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}
