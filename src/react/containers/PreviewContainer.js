import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { timeout, map } from 'rxjs/operators';

import { getPreview } from '../services/api';
import Loader from '../components/Loader';

/**
 * Pull the rendered HTML out of a preview ajax response.
 * @param {Object} res
 */
export const getResponseHtml = ( res ) => res.response.html;

const PreviewContainer = ( { config, getEntryContent } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ entryContent, setEntryContent ] = useState( false );

	useEffect( () => {
		const subscription = getPreview( getEntryContent(), config )
			.pipe( timeout( 10000 ), map( getResponseHtml ) )
			.subscribe( {
				next: ( html ) => {
					setEntryContent( html );
					setLoading( false );
				},
				error: () => {
					setEntryContent( false );
					setLoading( false );
				},
			} );

		return () => subscription.unsubscribe();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( loading ) {
		return (
			<div className="liveblog-preview">
				<Loader />
			</div>
		);
	}

	if ( ! entryContent ) {
		return false;
	}

	return (
		<div
			className="liveblog-preview"
			dangerouslySetInnerHTML={ { __html: entryContent } }
		/>
	);
};

PreviewContainer.propTypes = {
	getEntryContent: PropTypes.func,
	config: PropTypes.object,
};

export default PreviewContainer;
