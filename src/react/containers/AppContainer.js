/* global jQuery */
/* eslint-disable class-methods-use-this */
/* eslint-disable class-methods-use-this */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import * as apiActions from '../actions/apiActions';
import * as configActions from '../actions/configActions';
import * as eventsActions from '../actions/eventsActions';
import * as userActions from '../actions/userActions';
import Entries from '../components/Entries';
import PaginationContainer from '../containers/PaginationContainer';
import EventsContainer from '../containers/EventsContainer';
import UpdateButton from '../components/UpdateButton';
import UpdateCount from '../components/UpdateCount';
import StatusFilter from '../components/StatusFilter';
import Editor from '../components/Editor';

class AppContainer extends Component {
  constructor() {
    super();
    this.eventsContainer = document.getElementById('liveblog-key-events');
  }

  componentDidMount() {
    const { loadConfig, getEntries, getEvents, startPolling } = this.props;
    loadConfig(window.liveblog_settings);
    getEntries(1, window.location.hash);
    startPolling();
    if (this.eventsContainer) getEvents();
    // fire an event to let the WordPress theme know the live blog entries are present
    setTimeout(() => {
      jQuery(document).trigger('liveblog-loaded');
    }, 1000);
  }

  componentDidUpdate(prevProps) {
    const { scrollToEntry, loading } = this.props;
    // If there is a hash link to specific entry, scroll again once all entries are rendered.
    if (!loading && prevProps.loading) {
      const hashId = window.location.hash.split('#')[1];
      scrollToEntry(`id_${hashId}`);
    }
  }

  render() {
    const { page, loading, entries, polling, mergePolling, config, total } = this.props;
    const paginationTypeLoadMore = config.paginationType === 'loadMore';
    const canEdit = config.is_liveblog_editable === '1';
    const frontEndEditing = config.backend_liveblogging !== '1';
    const isAdmin = config.is_admin;
    const showEditor = isAdmin ||
      (
        (page === 1 || paginationTypeLoadMore) &&
        canEdit &&
        frontEndEditing
      );


    return (
      <div style={{ position: 'relative' }}>
        {
          showEditor &&
          <Editor
            isEditing={false}
            backend={config.backend_liveblogging}
            useTinyMCE={config.use_tinymce}
          />
        }
        <UpdateButton polling={polling} click={() => mergePolling()} />

        <div id="liveblog-action-wrapper">
          { isAdmin && <StatusFilter loading={loading} /> }
          { isAdmin && <UpdateCount entries={entries} config={config} total={total} /> }
        </div>

        <Entries loading={loading} entries={entries} config={config} />
        <PaginationContainer entries={entries} />
        {this.eventsContainer && <EventsContainer container={this.eventsContainer} title={this.eventsContainer.getAttribute('data-title')} />}
        { isAdmin && <UpdateCount entries={entries} config={config} total={total} /> }
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
  config: PropTypes.object,
  scrollToEntry: PropTypes.func,
  total: PropTypes.number,
};

const filterPollingEntries = (entries, config) => {
  const newEntries = [];

  if (config.is_admin) {
    return Object.keys(entries);
  }

  Object.keys(entries).forEach((key) => {
    if ('new' === entries[key].type) {
      newEntries.push(key);
    }
  });

  return newEntries;
};

const mapStateToProps = state => ({
  page: state.pagination.page,
  loading: state.api.loading,
  total: state.api.total,
  entries: Object.keys(state.api.entries)
    .map(key => state.api.entries[key]),
  polling: filterPollingEntries(state.polling.entries, state.config),
  config: state.config,
});

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...configActions,
    ...apiActions,
    ...eventsActions,
    ...userActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(AppContainer);
