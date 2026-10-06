import React from 'react';
import PropTypes from 'prop-types';
import { __ } from '@wordpress/i18n';
import { timeAgo } from '../utils/utils';

// Key event content may be raw entry HTML, so it can't live inside a <button>.
const onActivateKey = ( callback ) => ( e ) => {
	if ( e.key === 'Enter' || e.key === ' ' ) {
		e.preventDefault();
		callback();
	}
};

const Event = ( { event, click, onDelete, canEdit, locale } ) => (
	<li className="liveblog-event">
		<div className="liveblog-event-body">
			<div className="liveblog-event-meta">
				{ timeAgo( event.entry_time, locale ) }
			</div>
			<div>
				{ canEdit && (
					<button
						type="button"
						className="dashicons dashicons-no-alt liveblog-editor-delete"
						aria-label={ __( 'Remove key event', 'liveblog' ) }
						onClick={ onDelete }
					/>
				) }
				<span
					className="liveblog-event-content"
					role="button"
					tabIndex={ 0 }
					onClick={ click }
					onKeyDown={ onActivateKey( click ) }
					dangerouslySetInnerHTML={ {
						__html: event.key_event_content,
					} }
				/>
			</div>
		</div>
	</li>
);

Event.propTypes = {
	event: PropTypes.object,
	click: PropTypes.func,
	onDelete: PropTypes.func,
	canEdit: PropTypes.bool,
	locale: PropTypes.string,
};

export default Event;
