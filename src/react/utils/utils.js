/* eslint-disable import/prefer-default-export */

export const entryListChanged = (previous, current) =>
  previous.length !== current.length || previous[0].updated < current[0].updated;

export const entryArrayToObject = (array) => {
	let object = {};
	array.map(item => {
		object[item.id] = {
			...api[item.id],
			...item,
		};
	});
	return object;
}

export const entriesApplyUpdate = (entries, updates, loadMore) => {
	for (let update of updates) {
		let id = `id_${update.id}`;
		if ( update.type == 'new' ) {
			if ( loadMore ) {
				entries[id] = update;
			} else {
				entries = {
					[id]: update,
					...entries
				}
			}
		} else if ( update.type == 'update' && entries.hasOwnProperty(id) ) {
			entries[id] = update;
		} else if ( update.type == 'delete' ) {
			delete entries[id];
		}
	}
	return entries;
}

export const getLastOfObject = (object) => {
	return object[Object.keys(object)[Object.keys(object).length - 1]];
}

export const getFirstOfObject = (object) => {
	return object[Object.keys(object)[0]];
}

export const getOldestTimestamp = (current, updates) => {
	if ( updates.length == 0 ) {
		return current;
	}
	let updateTimestamp = getLastOfObject(updates).timestamp;
	if ( current === false ) {
		return updateTimestamp;
	}
	return Math.min(current, updateTimestamp);
} 

export const getNewestTimestamp = (current, updates) => {
	if ( updates.length == 0 ) {
		return current;
	}
	let updateTimestamp = getFirstOfObject(updates).timestamp;
	return Math.max(current, updateTimestamp);
} 

export const getCurrentTimestamp = () => {
	return Math.floor( Date.now() / 1000);
}