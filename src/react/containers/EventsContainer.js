import ReactDOM from 'react-dom';
import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import { __ } from '@wordpress/i18n';

import * as eventsActions from '../actions/eventsActions';
import { scrollToEntry } from '../actions/userActions';
import { isLoadMore } from './PaginationContainer';

import Event from '../components/Event';
import DeleteConfirmation from '../components/DeleteConfirmation';

const noEntries = {};

class EventsContainer extends Component {
	constructor( props ) {
		super( props );
		this.state = {
			showPopup: false,
			keyEventToRemove: null,
		};

		this.delete = ( key ) => {
			/* eslint no-alert: 0 */
			if (
				window.confirm(
					__(
						'Are you sure you want to delete this entry?',
						'liveblog'
					)
				)
			) {
				this.props.deleteEvent( key );
			}
		};
	}

	confirmDeletion( key ) {
		this.setState( {
			keyEventToRemove: key,
		} );

		this.togglePopup(); // Keep key here to bind to render of delete popup
	}

	deleteKeyEvent() {
		this.props.deleteEvent( this.state.keyEventToRemove );

		this.setState( {
			showPopup: ! this.state.showPopup,
		} );
	}

	togglePopup() {
		this.setState( {
			showPopup: ! this.state.showPopup,
		} );
	}

	jumpTo( id ) {
		// With load more the entry may already be on screen, so skip the fetch.
		if ( this.props.loadedEntries[ `id_${ id }` ] ) {
			this.props.scrollToEntry( `id_${ id }` );
			return;
		}
		this.props.jumpToEvent( id );
	}

	renderEvents() {
		const { events, canEdit, locale, title } = this.props;

		return (
			<div>
				{ title !== '' ? (
					<h2 className="widget-title">{ title }</h2>
				) : null }
				<ul className="liveblog-events">
					{ Object.keys( events ).map( ( key, i ) => (
						<Event
							key={ i }
							event={ events[ key ] }
							click={ () => this.jumpTo( events[ key ].id ) }
							onDelete={ () =>
								this.confirmDeletion( events[ key ] )
							}
							canEdit={ canEdit }
							locale={ locale }
						/>
					) ) }
				</ul>
				{ this.state.showPopup ? (
					<DeleteConfirmation
						text={ __(
							'Are you sure you want to remove this entry as a key event?',
							'liveblog'
						) }
						onConfirmDelete={ () => this.deleteKeyEvent() }
						onCancel={ this.togglePopup.bind( this ) }
					/>
				) : null }
			</div>
		);
	}

	render() {
		return ReactDOM.createPortal(
			this.renderEvents(),
			this.props.container
		);
	}
}

EventsContainer.propTypes = {
	getEvents: PropTypes.func,
	deleteEvent: PropTypes.func,
	jumpToEvent: PropTypes.func,
	scrollToEntry: PropTypes.func,
	loadedEntries: PropTypes.object,
	events: PropTypes.object,
	container: PropTypes.any,
	canEdit: PropTypes.bool,
	locale: PropTypes.string,
	title: PropTypes.string,
};

const mapStateToProps = ( state ) => ( {
	locale: state.config.locale,
	events: state.events.entries,
	canEdit: state.config.is_liveblog_editable === '1',
	loadedEntries: isLoadMore(
		state.config.pagination_type,
		state.pagination.page
	)
		? state.api.entries
		: noEntries,
} );

const mapDispatchToProps = ( dispatch ) =>
	bindActionCreators(
		{
			...eventsActions,
			scrollToEntry,
		},
		dispatch
	);

export default connect(
	mapStateToProps,
	mapDispatchToProps
)( EventsContainer );
