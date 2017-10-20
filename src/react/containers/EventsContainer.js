import ReactDOM from 'react-dom';
import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import * as eventsActions from '../actions/eventsActions';

import Event from '../components/Event';

class EventsContainer extends Component {
  renderEvents() {
    const { entries, deleteEvent, jumpToEvent } = this.props;

    return (
      <div>
        <h1>Key Events</h1>
        <ul>
          {Object.keys(entries).map((key, i) =>
            <Event
              key={i}
              event={entries[key]}
              click={() => jumpToEvent(entries[key].id)}
              onDelete={() => deleteEvent(entries[key])}
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
  entries: PropTypes.object,
  container: PropTypes.any,
};

// Map state to props on connected component
const mapStateToProps = state => ({
  entries: state.events.entries,
});

// Map dispatch/actions to props on connected component
const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...eventsActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EventsContainer);
