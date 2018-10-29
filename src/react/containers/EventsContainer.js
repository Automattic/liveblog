import ReactDOM from 'react-dom';
import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import * as eventsActions from '../actions/eventsActions';

import Event from '../components/Event';
import DeleteConfirmation from '../components/DeleteConfirmation';

class EventsContainer extends Component {
  constructor(props) {
    super(props);
    this.state = {
      showPopup: false,
      keyEventToRemove: null,
    };

    this.delete = (key) => {
      /* eslint no-alert: 0 */
      if (window.confirm('Are you sure you want to delete this entry?')) {
        this.props.deleteEvent(key);
      }
    };
  }

  confirmDeletion(key) {
    this.setState({
      keyEventToRemove: key,
    });

    this.togglePopup(); // Keep key here to bind to render of delete popup
  }

  deleteKeyEvent() {
    this.props.deleteEvent(this.state.keyEventToRemove);

    this.setState({
      showPopup: !this.state.showPopup,
    });
  }

  togglePopup() {
    this.setState({
      showPopup: !this.state.showPopup,
    });
  }

  renderEvents() {
    const { events, jumpToEvent, canEdit, utcOffset, dateFormat, title } = this.props;

    return (
      <div>
        { (title !== '') ? <h2 className="widget-title">{title}</h2> : null }
        <ul className="liveblog-events">
          {Object.keys(events).map((key, i) =>
            <Event
              key={i}
              event={events[key]}
              click={() => jumpToEvent(events[key].id)}
              onDelete={() => this.confirmDeletion(events[key])}
              canEdit={canEdit}
              utcOffset={utcOffset}
              dateFormat={dateFormat}
            />,
          )}
        </ul>
        {this.state.showPopup ?
          <DeleteConfirmation
            text="Are you sure you want to remove this entry as a key event?"
            onConfirmDelete={() => this.deleteKeyEvent()}
            onCancel={this.togglePopup.bind(this)}
          />
          : null
        }
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
  utcOffset: PropTypes.string,
  dateFormat: PropTypes.string,
  title: PropTypes.string,
};

const mapStateToProps = state => ({
  dateFormat: state.config.date_format,
  utcOffset: state.config.utc_offset,
  events: state.events.entries,
  canEdit: state.config.is_liveblog_editable === '1',
});

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...eventsActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EventsContainer);
