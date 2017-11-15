import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class PaginationContainer extends Component {
  render() {
    const { page, pages, getEntries } = this.props;

    return (
      <div className="liveblog-pagination">
        <div>
          <button
            disabled={page === 1}
            className="liveblog-btn liveblog-pagination-btn liveblog-pagination-first"
            onClick={() => getEntries(1)}
          >
            First
          </button>
          <button
            disabled={page === 1}
            className="liveblog-btn liveblog-pagination-btn liveblog-pagination-prev"
            onClick={() => getEntries(page - 1)}
          >
            Prev
          </button>
        </div>
        <span className="liveblog-pagination-pages">Page {page} of {pages}</span>
        <div>
          <button
            disabled={page === pages}
            className="liveblog-btn liveblog-pagination-btn liveblog-pagination-next"
            onClick={() => getEntries(page + 1)}
          >
            Next
          </button>
          <button
            disabled={page === pages}
            className="liveblog-btn liveblog-pagination-btn liveblog-pagination-last"
            onClick={() => getEntries(pages)}
          >
            Last
          </button>
        </div>
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

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(PaginationContainer);
