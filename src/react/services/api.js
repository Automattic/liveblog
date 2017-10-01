/**
 * RxJS ajax pretty much interchangable with axios.
 * Using RxJS ajax as it's already an observable to use with redux-observable.
 */
import { ajax } from 'rxjs/observable/dom/ajax';
import * as actions from '../actions/apiActions';
/**
 * Example api method.
 */
export function getEntries( lastEntry, config ) {
  const settings = {
    url: `${config.api}/post/${config.post_id}/entries/${lastEntry.id}-${lastEntry.updated}`,
    method: 'GET',
  };

  return ajax(settings);
}

export function startPolling( timestamp, config ) {
  const settings = {
    url: `${config.api}/post/${config.post_id}/polling/${timestamp}` ,
    method: 'GET',
  };

  return ajax(settings);
}

export function createEntry( entry, config, nonce = false ) {
  if ( nonce === false ) {
  	nonce = config.nonce;
  }
  const settings = {
    url: `${config.api}/post/${config.post_id}/entry`,
    method: 'POST',
    body: entry,
    headers: {
        "X-WP-Nonce": nonce,
        "cache-control": "no-cache"
    }
  };

  return ajax(settings);
}

export function updateEntry( entry, config, nonce = false ) {
  if ( nonce === false ) {
  	nonce = config.nonce;
  }
  const settings = {
    url: `${config.api}/post/${config.post_id}/entry/${entry.id}`,
    method: 'PATCH',
    body: entry,
    headers: {
        "X-WP-Nonce": nonce,
        "cache-control": "no-cache"
    }
  };

  return ajax(settings);
}

export function deleteEntry( id, config, nonce = false ) {
  if ( nonce === false ) {
  	nonce = config.nonce;
  }
  const settings = {
    url: `${config.api}/post/${config.post_id}/entry/${id}`,
    method: 'DELETE',
    headers: {
        "X-WP-Nonce": nonce,
        "cache-control": "no-cache"
    }
  };

  return ajax(settings);
}

export function examinePolling( store ) {
	const state = store.getState();
	const previous = state.api.previousPolling;
	const current = state.api.polling;

	let fetchNeeded = false;

	//entry has been added or deleted
	if ( previous.length != current.length ) {
		fetchNeeded = true;
	} else {
		//entry has been updated
		if ( previous[0].updated < current[0].updated ) {
			fetchNeeded = true;
		}
	}

	if ( fetchNeeded ) {
		store.dispatch(actions.getEntries(current[0]));
	}

	return [];
}

export function getEntriesAfterCRUD( store ) {
	const state = store.getState();
	store.dispatch(actions.getEntries(state.api.lastEntry));
	return [];
}