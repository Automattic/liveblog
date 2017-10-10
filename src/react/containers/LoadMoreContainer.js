import React, { Component } from 'react';
import PropTypes from 'prop-types';

// Redux
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

// Actions
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class LoadMoreContainer extends Component {
  constructor(props) {
    super(props);

    this.loadMore = () => this.props.getEntries( this.props.api.oldestEntryTimestamp );
  }

  render() {
    return (
     <div>
      <button className="wpcom-liveblog-load-more" onClick={this.loadMore}>Load More</button>
     </div>
    );
  }
}

LoadMoreContainer.propTypes = {

};

// Map state to props on connected component
const mapStateToProps = state => state;

// Map dispatch/actions to props on connected component
const mapDispatchToProps = dispatch =>
  bindActionCreators({ ...apiActions, ...userActions }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(LoadMoreContainer);
