import React, { Component } from 'react';

// Redux
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

// Actions
import * as apiActions from '../actions/apiActions';
import * as configActions from '../actions/configActions';

// Component to connect to store
import EditorContainer from '../containers/EditorContainer';
import Entries from '../components/Entries';

import '../../styles/app.scss';

class AppContainer extends Component {
  componentDidMount() {
    this.props.loadConfig( window.wpcomLiveblog );
    this.props.getEntries( window.wpcomLiveblog.last_entry );
    this.props.startPolling( window.wpcomLiveblog.timestamp );
  }

  render() {
    if (this.props.api.entries.length === 0) {
      return (
        <div>Loading...</div>
      )
    }

    return (
      <div>
        <EditorContainer />
        <Entries entries={this.props.api.entries} />
      </div>
    )
  }
}

// Map state to props on connected component
// Ideally pick out pieces of state rather than full object
const mapStateToProps = (state) => state

// Map dispatch/actions to props on connected component
const mapDispatchToProps = (dispatch) => bindActionCreators({
  ...configActions,
  ...apiActions,
}, dispatch)

export default connect(mapStateToProps, mapDispatchToProps)(AppContainer);
