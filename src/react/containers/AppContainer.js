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

  componentWillMount() {
    const script = document.createElement('script');
    script.src = 'https://platform.twitter.com/widgets.js';
    script.async = true;
    document.body.appendChild(script);

    const script2 = document.createElement('script');
    script2.src = 'https://platform.instagram.com/en_US/embeds.js';
    script2.async = true;
    document.body.appendChild(script2);

    const script3 = document.createElement('script');
    script3.src = 'https://embed.redditmedia.com/widgets/platform.js';
    script3.async = true;
    document.body.appendChild(script3);
  }

  componentDidUpdate() {
    if (window.instgrm) {
      window.instgrm.Embeds.process();
    }

    if (window.twttr) {
      document.querySelectorAll('.twitter-tweet').forEach((ele) => {
        window.twttr.widgets.load(ele);
      });
    }
  }

  componentDidMount() {
    const { loadConfig, getEntries, getEvents, startPolling } = this.props;
    loadConfig(window.liveblog_settings);
    getEntries(1);
    startPolling();
    this.listenForEmbeds();
    if (this.eventsContainer) getEvents();
  }

  render() {
    const { page, loading, entries, polling, mergePolling, config } = this.props;
    const canEdit = config.is_liveblog_editable === '1';

    return (
      <div style={{ position: 'relative' }}>
        {(page === 1 && canEdit) && <EditorContainer isEditing={false} />}
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
  config: PropTypes.object,
};

const mapStateToProps = state => ({
  page: state.pagination.page,
  loading: state.api.loading,
  entries: Object.keys(state.api.entries)
    .map(key => state.api.entries[key])
    .slice(0, state.config.entries_per_page),
  polling: Object.keys(state.polling.entries),
  config: state.config,
});

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...configActions,
    ...apiActions,
    ...eventsActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(AppContainer);
