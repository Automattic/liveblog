import React, { Component } from 'react';
import PropTypes from 'prop-types';

// Redux
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

// Actions
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class PaginationContainer extends Component {
  render() {
    const { page, pages, getEntries } = this.props;

    return (
      <div>
        <button
          disabled={page === 1}
          className="wpcom-liveblog-load-more"
          onClick={() => getEntries(page - 1)}
        >
          Previous
        </button>
        <div>Page {page} of {pages}</div>
        <button
          disabled={page === pages}
          className="wpcom-liveblog-load-more"
          onClick={() => getEntries(page + 1)}
        >
          Next
        </button>
      </div>
    );
  }
}

PaginationContainer.propTypes = {
  page: PropTypes.number,
  pages: PropTypes.number,
  getEntries: PropTypes.func,
};

const mapStateToProps = state => ({
  page: state.pagination.page,
  pages: state.pagination.pages,
});

// Map dispatch/actions to props on connected component
const mapDispatchToProps = dispatch =>
  bindActionCreators({ ...apiActions, ...userActions }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(PaginationContainer);
