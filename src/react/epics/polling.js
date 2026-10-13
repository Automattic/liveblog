import { combineEpics, ofType } from 'redux-observable';
import { of, interval, concat } from 'rxjs';
import {
	switchMap,
	takeUntil,
	exhaustMap,
	timeout,
	map,
	catchError,
	mergeMap,
} from 'rxjs/operators';
import types from '../actions/actionTypes';

import {
	pollingSuccess,
	pollingFailed,
	mergePollingIntoEntries,
	getEntriesSuccess,
	getEntriesFailed,
} from '../actions/apiActions';

import { polling as pollingApi, getEntries } from '../services/api';

import { scrollToEntry } from '../actions/userActions';

import { shouldRenderNewEntries } from '../utils/utils';

export const startPollingEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType( types.START_POLLING ),
		switchMap( () =>
			interval( state$.value.config.refresh_interval * 1000 ).pipe(
				takeUntil( action$.pipe( ofType( types.CANCEL_POLLING ) ) ),
				exhaustMap( () =>
					pollingApi(
						state$.value.polling.newestEntry.timestamp,
						state$.value.config
					).pipe(
						timeout( 10000 ),
						map( ( res ) =>
							pollingSuccess(
								res.response,
								shouldRenderNewEntries(
									state$.value.pagination.page,
									state$.value.api.entries,
									state$.value.polling.entries,
									state$.value.pagination.pages,
									state$.value.config.entry_order === 'asc'
								)
							)
						),
						catchError( ( error ) => of( pollingFailed( error ) ) )
					)
				)
			)
		)
	);

export const mergePollingEpic = ( action$, state$ ) =>
	action$.pipe(
		ofType( types.MERGE_POLLING ),
		switchMap( () => {
			const { pagination, polling, config } = state$.value;
			const entries = Object.keys( polling.entries ).map(
				( key ) => polling.entries[ key ]
			);
			const pages = Math.max( pagination.pages, polling.pages );
			// New entries live on the last page when shown oldest first.
			const targetPage = config.entry_order === 'asc' ? pages : 1;

			if ( pagination.page === targetPage ) {
				return concat(
					of( mergePollingIntoEntries( entries, pages ) ),
					of(
						scrollToEntry(
							`id_${ entries[ entries.length - 1 ].id }`
						)
					)
				);
			}

			return getEntries( targetPage, config, polling.newestEntry ).pipe(
				timeout( 10000 ),
				mergeMap( ( res ) =>
					concat(
						of( getEntriesSuccess( res.response, true ) ),
						of( scrollToEntry( `id_${ polling.newestEntry.id }` ) )
					)
				),
				catchError( ( error ) => of( getEntriesFailed( error ) ) )
			);
		} )
	);

export default combineEpics( startPollingEpic, mergePollingEpic );
