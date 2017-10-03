/**
 * RxJS ajax pretty much interchangable with axios.
 * Using RxJS ajax as it's already an observable to use with redux-observable.
 */
import { ajax } from 'rxjs/observable/dom/ajax';

export function getEntries(lastEntry, config) {
  const settings = {
    url: `${config.api}/post/${config.post_id}/entries/${lastEntry.id}-${lastEntry.updated}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function startPolling(timestamp, config) {
  const settings = {
    url: `${config.api}/post/${config.post_id}/polling/${timestamp}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function createEntry(entry, config, nonce = false) {
  const settings = {
    url: `${config.api}/post/${config.post_id}/entry`,
    method: 'POST',
    body: entry,
    headers: {
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}

export function updateEntry(entry, config, nonce = false) {
  const settings = {
    url: `${config.api}/post/${config.post_id}/entry/${entry.id}`,
    method: 'PATCH',
    body: entry,
    headers: {
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}

export function deleteEntry(id, config, nonce = false) {
  const settings = {
    url: `${config.api}/post/${config.post_id}/entry/${id}`,
    method: 'DELETE',
    headers: {
      'X-WP-Nonce': nonce || config.nonce,
      'cache-control': 'no-cache',
    },
  };

  return ajax(settings);
}
