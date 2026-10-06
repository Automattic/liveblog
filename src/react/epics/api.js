import { combineEpics, ofType } from 'redux-observable';
import { of, concat } from 'rxjs';
import { switchMap, timeout, map, catchError, mergeMap } from 'rxjs/operators';

import types from '../actions/actionTypes';

import {
	getEntriesSuccess,
	getEntriesFailed,
	pollingSuccess,
	createEntrySuccess,
	createEntryFailed,
	updateEntrySuccess,
	updateEntryFailed,
	deleteEntrySuccess,
	deleteEntryFailed,
} from '../actions/apiActions';

import { jumpToEvent } from '../actions/eventsActions';

import {
	getEntries,
	createEntry,
	updateEntry,
	deleteEntry,
} from '../services/api';

import { shouldRenderNewEntries, getScrollToId } from '../utils/utils';

import { scrollToEntry } from '../actions/userActions';

export const getEntriesEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType( types.GET_ENTRIES ),
		switchMap( ( { page, hash } ) => {
			/**
			 * If there is a has in the url, we check that it is a number
			 * and then we jump to the that entry. If the number isn't a valid
			 * id of an entry the jumpToEvent api will return the first page.
			 */
			if ( hash ) {
				const id = hash.split( '#' )[ 1 ];
				if ( ! isNaN( id ) ) {
					return of( jumpToEvent( id ) );
				}
			}

			return getEntries(
				page,
				state$.value.config,
				state$.value.api.newestEntry
			).pipe(
				timeout( 10000 ),
				map( ( res ) =>
					getEntriesSuccess(
						res.response,
						shouldRenderNewEntries(
							state$.value.pagination.page,
							state$.value.api.entries,
							state$.value.polling.entries
						)
					)
				),
				catchError( ( error ) => of( getEntriesFailed( error ) ) )
			);
		} )
	);

export const getPaginatedEntriesEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType( types.GET_ENTRIES_PAGINATED ),
		switchMap( ( { page, scrollTo } ) =>
			getEntries(
				page,
				state$.value.config,
				state$.value.api.newestEntry
			).pipe(
				timeout( 10000 ),
				mergeMap( ( res ) =>
					concat(
						of(
							getEntriesSuccess(
								res.response,
								shouldRenderNewEntries(
									state$.value.pagination.page,
									state$.value.api.entries,
									state$.value.polling.entries
								)
							)
						),
						of(
							scrollToEntry(
								getScrollToId(
									res.response.entries,
									scrollTo,
									res.response.page
								)
							)
						)
					)
				),
				catchError( ( error ) => of( getEntriesFailed( error ) ) )
			)
		)
	);

export const createEntryEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType( types.CREATE_ENTRY ),
		switchMap( ( { payload } ) =>
			createEntry(
				payload,
				state$.value.config,
				state$.value.api.nonce
			).pipe(
				timeout( 10000 ),
				map( ( res ) => createEntrySuccess( res.response ) ),
				catchError( ( error ) => of( createEntryFailed( error ) ) )
			)
		)
	);

export const updateEntryEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType( types.UPDATE_ENTRY ),
		switchMap( ( { payload } ) =>
			updateEntry(
				payload,
				state$.value.config,
				state$.value.api.nonce
			).pipe(
				timeout( 10000 ),
				map( ( res ) => updateEntrySuccess( res.response ) ),
				catchError( ( error ) => of( updateEntryFailed( error ) ) )
			)
		)
	);

export const deleteEntryEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType( types.DELETE_ENTRY ),
		switchMap( ( { payload } ) =>
			deleteEntry(
				payload,
				state$.value.config,
				state$.value.api.nonce
			).pipe(
				timeout( 10000 ),
				map( ( res ) => deleteEntrySuccess( res.response ) ),
				catchError( ( error ) => of( deleteEntryFailed( error ) ) )
			)
		)
	);

export const getEntriesAfterChangeEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType(
			types.CREATE_ENTRY_SUCCESS,
			types.UPDATE_ENTRY_SUCCESS,
			types.DELETE_ENTRY_SUCCESS
		),
		// Forcing a render clears the pending queue, so render the queued
		// (older) entries along with the change rather than dropping them.
		map( ( { payload } ) =>
			pollingSuccess(
				{
					...payload,
					entries: [
						...Object.values( state$.value.polling.entries ),
						...payload.entries,
					],
				},
				true
			)
		)
	);

export default combineEpics(
	getEntriesEpic,
	getPaginatedEntriesEpic,
	createEntryEpic,
	updateEntryEpic,
	deleteEntryEpic,
	getEntriesAfterChangeEpic
);
