import React from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { getEntriesPaginated } from '../actions/apiActions';

/**
 * Work out button disabled state from the current page/pages.
 * @param {number} page
 * @param {number} pages
 */
export const getPaginationState = ( page, pages ) => ( {
	isFirstPage: page === 1,
	isLastPage: page === pages,
} );

const PaginationContainer = () => {
	const page = useSelector( ( state ) => state.pagination.page );
	const pages = useSelector( ( state ) => state.pagination.pages );
	const dispatch = useDispatch();

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
