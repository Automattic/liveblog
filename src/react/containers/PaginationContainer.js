import React from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { getEntriesPaginated, loadMoreEntries } from '../actions/apiActions';

/**
 * Work out button disabled state from the current page/pages.
 * @param {number} page
 * @param {number} pages
 */
export const getPaginationState = ( page, pages ) => ( {
	isFirstPage: page === 1,
	isLastPage: page === pages,
} );

/**
 * Load more only applies while showing the newest entries. Jumping to a key
 * event further down replaces the list, so normal page links are used there.
 * @param {string} type
 * @param {number} page
 */
export const isLoadMore = ( type, page ) => type === 'load-more' && page === 1;

/**
 * Work out the next page to fetch from the number of entries already shown.
 * Rounding down can refetch some shown entries, which are merged by id, but
 * never skips any when new entries have been added above.
 * @param {number} loaded
 * @param {number} perPage
 */
export const getLoadMorePage = ( loaded, perPage ) =>
	Math.floor( loaded / perPage ) + 1;

const LoadMore = () => {
	const loaded = useSelector(
		( state ) => Object.keys( state.api.entries ).length
	);
	const hasMore = useSelector( ( state ) => state.pagination.hasMore );
	const perPage = useSelector( ( state ) =>
		parseInt( state.config.entries_per_page, 10 )
	);
	const loadingMore = useSelector( ( state ) => state.api.loadingMore );
	const dispatch = useDispatch();

	if ( ! hasMore ) {
		return null;
	}

	return (
		<div className="liveblog-pagination liveblog-pagination--load-more">
			<button
				disabled={ loadingMore }
				className="liveblog-btn liveblog-pagination-btn liveblog-pagination-load-more"
				onClick={ () =>
					dispatch(
						loadMoreEntries( getLoadMorePage( loaded, perPage ) )
					)
				}
			>
				{ loadingMore
					? __( 'Loading…', 'liveblog' )
					: __( 'Load more', 'liveblog' ) }
			</button>
		</div>
	);
};

const PaginationContainer = () => {
	const page = useSelector( ( state ) => state.pagination.page );
	const pages = useSelector( ( state ) => state.pagination.pages );
	const type = useSelector( ( state ) => state.config.pagination_type );
	const dispatch = useDispatch();

	if ( isLoadMore( type, page ) ) {
		return <LoadMore />;
	}

	const { isFirstPage, isLastPage } = getPaginationState( page, pages );

	return (
		<div className="liveblog-pagination">
			<div>
				<button
					disabled={ isFirstPage }
					className={ `liveblog-btn liveblog-pagination-btn liveblog-pagination-first ${
						isFirstPage ? 'liveblog-btn--hide' : ''
					}` }
					onClick={ () =>
						dispatch( getEntriesPaginated( 1, 'first' ) )
					}
				>
					{ __( 'First', 'liveblog' ) }
				</button>
				<button
					disabled={ isFirstPage }
					className={ `liveblog-btn liveblog-pagination-btn liveblog-pagination-prev ${
						isFirstPage ? 'liveblog-btn--hide' : ''
					}` }
					onClick={ () =>
						dispatch( getEntriesPaginated( page - 1, 'last' ) )
					}
				>
					{ __( 'Prev', 'liveblog' ) }
				</button>
			</div>
			<span className="liveblog-pagination-pages">
				{ page } of { pages }
			</span>
			<div>
				<button
					disabled={ isLastPage }
					className={ `liveblog-btn liveblog-pagination-btn liveblog-pagination-next ${
						isLastPage ? 'liveblog-btn--hide' : ''
					}` }
					onClick={ () =>
						dispatch( getEntriesPaginated( page + 1, 'first' ) )
					}
				>
					{ __( 'Next', 'liveblog' ) }
				</button>
				<button
					disabled={ isLastPage }
					className={ `liveblog-btn liveblog-pagination-btn liveblog-pagination-last ${
						isLastPage ? 'liveblog-btn--hide' : ''
					}` }
					onClick={ () =>
						dispatch( getEntriesPaginated( pages, 'first' ) )
					}
				>
					{ __( 'Last', 'liveblog' ) }
				</button>
			</div>
		</div>
	);
};

export default PaginationContainer;
