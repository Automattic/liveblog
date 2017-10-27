import ReactDOM from 'react-dom';
import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import * as eventsActions from '../actions/eventsActions';

import Event from '../components/Event';

class EventsContainer extends Component {
  renderEvents() {
    const { events, deleteEvent, jumpToEvent, canEdit } = this.props;

    return (
      <div>
        <h1>Key Events</h1>
        <ul>
          {Object.keys(events).map((key, i) =>
            <Event
              key={i}
              event={events[key]}
              click={() => jumpToEvent(events[key].id)}
              onDelete={() => deleteEvent(events[key])}
              canEdit={canEdit}
            />,
          )}
        </ul>
      </div>
    );
  }

  render() {
    return ReactDOM.createPortal(
      this.renderEvents(),
      this.props.container,
    );
  }
}

EventsContainer.propTypes = {
  getEvents: PropTypes.func,
  deleteEvent: PropTypes.func,
  jumpToEvent: PropTypes.func,
  events: PropTypes.object,
  container: PropTypes.any,
  canEdit: PropTypes.bool,
};

const mapStateToProps = state => ({
  events: state.events.entries,
  canEdit: state.config.is_liveblog_editable === '1',
});

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...eventsActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EventsContainer);
