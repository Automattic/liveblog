import React, { Component, Suspense } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import * as apiActions from '../actions/apiActions';
import * as configActions from '../actions/configActions';
import * as eventsActions from '../actions/eventsActions';
import Entries from '../components/Entries';
import PaginationContainer from '../containers/PaginationContainer';
import EventsContainer from '../containers/EventsContainer';
import UpdateButton from '../components/UpdateButton';
import Editor from '../components/Editor';

class AppContainer extends Component {
	constructor() {
		super();
		this.eventsContainer = document.getElementById( 'liveblog-key-events' );
	}

	componentDidMount() {
		const { loadConfig, getEntries, getEvents, startPolling } = this.props;
		loadConfig( window.liveblog_settings );
		getEntries( 1, window.location.hash );
		// Don't poll if the liveblog is archived - there won't be new entries
		if ( window.liveblog_settings.state !== 'archive' ) {
			startPolling();
		}
		if ( this.eventsContainer ) {
			getEvents();
		}
	}

	render() {
		const {
			page,
			pages,
			loading,
			entries,
			polling,
			mergePolling,
			config,
			total,
		} = this.props;
		const canEdit = config.is_liveblog_editable === '1';
		// New entries are added to the last page when oldest first, so the editor goes there.
		const showEditor =
			canEdit && page === ( config.entry_order === 'asc' ? pages : 1 );

		return (
			<div style={ { position: 'relative' } }>
				{ showEditor && (
					<Suspense fallback={ <div>Loading editor...</div> }>
						<Editor isEditing={ false } />
					</Suspense>
				) }
				<UpdateButton
					polling={ polling }
					click={ () => mergePolling() }
				/>
				{ canEdit && (
					<div className="liveblog-updates-count">
						Updates: { total }
					</div>
				) }
				<PaginationContainer />
				<Entries loading={ loading } entries={ entries } />
				<PaginationContainer />
				{ this.eventsContainer && (
					<EventsContainer
						container={ this.eventsContainer }
						title={ this.eventsContainer.getAttribute(
							'data-title'
						) }
					/>
				) }
			</div>
		);
	}
}

AppContainer.propTypes = {
	loadConfig: PropTypes.func,
	getEntries: PropTypes.func,
	getEvents: PropTypes.func,
	startPolling: PropTypes.func,
	api: PropTypes.object,
	entries: PropTypes.array,
	page: PropTypes.number,
	pages: PropTypes.number,
	loading: PropTypes.bool,
	polling: PropTypes.array,
	mergePolling: PropTypes.func,
	config: PropTypes.object,
	total: PropTypes.number,
};

export const getVisibleEntries = ( state ) => {
	const entries = Object.keys( state.api.entries ).map(
		( key ) => state.api.entries[ key ]
	);

	// Oldest first adds new entries at the bottom. Trimming the top would move the
	// content the reader is looking at, so keep them all until the page changes.
	if ( state.config.entry_order === 'asc' ) {
		return entries;
	}

	return entries.slice( 0, state.config.entries_per_page );
};

const mapStateToProps = ( state ) => ( {
	page: state.pagination.page,
	pages: state.pagination.pages,
	loading: state.api.loading,
	entries: getVisibleEntries( state ),
	polling: Object.keys( state.polling.entries ),
	config: state.config,
	total: state.pagination.total,
} );

const mapDispatchToProps = ( dispatch ) =>
	bindActionCreators(
		{
			...configActions,
			...apiActions,
			...eventsActions,
		},
		dispatch
	);

export default connect( mapStateToProps, mapDispatchToProps )( AppContainer );
