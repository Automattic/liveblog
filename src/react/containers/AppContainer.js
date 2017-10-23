import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as configActions from '../actions/configActions';
import * as eventsActions from '../actions/eventsActions';
import EditorContainer from '../containers/EditorContainer';
import Entries from '../components/Entries';
import PaginationContainer from '../containers/PaginationContainer';
import EventsContainer from '../containers/EventsContainer';
import UpdateButton from '../components/UpdateButton';

class AppContainer extends Component {
  constructor() {
    super();
    this.eventsContainer = document.getElementById('liveblog-key-events');
  }

  componentDidMount() {
    const { loadConfig, getEntries, getEvents, startPolling } = this.props;
    loadConfig(window.liveblog_settings);
    getEntries(1);
    startPolling();
    if (this.eventsContainer) getEvents();
  }

  render() {
    const { page, loading, entries, polling, mergePolling } = this.props;

    return (
      <div style={{ position: 'relative' }}>
        {page === 1 && <EditorContainer isEditing={false} />}
        <UpdateButton polling={polling} click={() => mergePolling()} />
        <PaginationContainer />
        <Entries loading={loading} entries={entries} />
        <PaginationContainer />
        {this.eventsContainer && <EventsContainer container={this.eventsContainer} />}
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
  loading: PropTypes.bool,
  polling: PropTypes.array,
  mergePolling: PropTypes.func,
};

const mapStateToProps = state => ({
  page: state.pagination.page,
  loading: state.api.loading,
  entries: Object.keys(state.api.entries)
    .map(key => state.api.entries[key])
    .slice(0, state.pagination.entriesPerPage),
  polling: Object.keys(state.polling.entries),
});

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...configActions,
    ...apiActions,
    ...eventsActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(AppContainer);
