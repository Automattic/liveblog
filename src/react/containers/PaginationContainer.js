import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class PaginationContainer extends Component {
  render() {
    const { page, pages, getEntriesPaginated } = this.props;

    const isFirstPage = (page === 1);
    const isLastPage = (page === pages);

    return (
      <div className="liveblog-pagination">
        <div>
          <button
            disabled={isFirstPage}
            className={`liveblog-btn liveblog-pagination-btn liveblog-pagination-first ${isFirstPage && 'liveblog-btn--hide'}`}
            onClick={() => getEntriesPaginated(1, 'first')}
          >
            First
          </button>
          <button
            disabled={isFirstPage}
            className={`liveblog-btn liveblog-pagination-btn liveblog-pagination-prev ${isFirstPage && 'liveblog-btn--hide'}`}
            onClick={() => getEntriesPaginated((page - 1), 'last')}
          >
            Prev
          </button>
        </div>
        <span className="liveblog-pagination-pages">{page} of {pages}</span>
        <div>
          <button
            disabled={isLastPage}
            className={`liveblog-btn liveblog-pagination-btn liveblog-pagination-next ${isLastPage && 'liveblog-btn--hide'}`}
            onClick={() => getEntriesPaginated((page + 1), 'first')}
          >
            Next
          </button>
          <button
            disabled={isLastPage}
            className={`liveblog-btn liveblog-pagination-btn liveblog-pagination-last ${isLastPage && 'liveblog-btn--hide'}`}
            onClick={() => getEntriesPaginated(pages, 'first')}
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
  getEntriesPaginated: PropTypes.func,
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
