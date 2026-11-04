import { getPollingPages } from '../utils/utils';

export const initialState = {
	page: 1,
	pages: 1,
	total: 0,
	entryOrder: 'desc',
};

export const pagination = ( state = initialState, action ) => {
	switch ( action.type ) {
		case 'GET_ENTRIES':
			return {
				...state,
				page: action.page,
			};

		case 'GET_ENTRIES_SUCCESS':
			return {
				...state,
				pages: Math.max( action.payload.pages, 1 ),
				page: action.payload.page,
				total: action.payload.total || 0,
			};

		case 'MERGE_POLLING_INTO_ENTRIES':
			// Count new entries from payload (entries with type 'new')
			const newCount = action.payload.filter(
				( e ) => e.type === 'new'
			).length;
			return {
				...state,
				pages: action.pages,
				total: state.total + newCount,
			};

		case 'CREATE_ENTRY_SUCCESS':
			return {
				...state,
				total: state.total + 1,
			};

		case 'DELETE_ENTRY_SUCCESS':
			return {
				...state,
				total: Math.max( 0, state.total - 1 ),
			};

		case 'POLLING_SUCCESS':
			// Oldest first keeps adding to the page the reader is on, so keep it as the
			// last page. Otherwise live updates would stop once that page is full.
			return {
				...state,
				pages:
					action.renderNewEntries && state.entryOrder !== 'asc'
						? getPollingPages( state.pages, action.payload.pages )
						: state.pages,
			};

		case 'LOAD_CONFIG':
			return {
				...state,
				entryOrder: action.payload.entry_order,
			};

		default:
			return state;
	}
};
