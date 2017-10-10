import React, { Component } from 'react';
import PropTypes from 'prop-types';

// Redux
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

// Actions
import * as apiActions from '../actions/apiActions';
import * as configActions from '../actions/configActions';

// Component to connect to store
import EditorContainer from '../containers/EditorContainer';
import Entries from '../components/Entries';
import LoadMoreContainer from '../containers/LoadMoreContainer';

import '../../styles/app.scss';

class AppContainer extends Component {
  componentDidMount() {
    const { loadConfig, getEntries, startPolling } = this.props;

    loadConfig(window.liveblog_settings);
    getEntries(0);
    startPolling();
  }

  renderEntries() {
    const { api } = this.props;

    if (api.entries.length === 0) {
      return <div>Loading...</div>;
    }

    return <Entries entries={api.entries} />;
  }

  render() {
    return (
      <div>
        <EditorContainer />
        {this.renderEntries()}
        <LoadMoreContainer />
      </div>
    );
  }
}

AppContainer.propTypes = {
  loadConfig: PropTypes.func,
  getEntries: PropTypes.func,
  startPolling: PropTypes.func,
  api: PropTypes.object,
};

// Map state to props on connected component
const mapStateToProps = state => ({
  api: state.api,
});

// Map dispatch/actions to props on connected component
const mapDispatchToProps = dispatch =>
  bindActionCreators({ ...configActions, ...apiActions }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(AppContainer);
