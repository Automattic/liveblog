import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class PaginationContainer extends Component {
  render() {
    const { page, pages, getEntriesPaginated } = this.props;

    // Don't diplsay pagination if we only have a single page.
    if (pages === 1) {
      return null;
    }

    const onClick = (e) => {
      e.preventDefault();
      console.log(e.currentTarget.getAttribute('index'));
      getEntriesPaginated((e.currentTarget.getAttribute('index')), 'first');
    };

    let i;
    const pageNavigation = [];
    for (i = 1; i <= pages; i += 1) {
      if (page === i) {
        pageNavigation.push(
          React.createElement(
            'span',
            {
              key: i,
              className: 'liveblog-page-navigation-link current',
            },
            i,
          ),
        );
      } else {
        pageNavigation.push(
          React.createElement(
            'a',
            {
              href: '#',
              onClick,
              index: i,
              key: i,
              className: 'liveblog-page-navigation-link',
            },
            i,
          ),
        );
      }
    }

    return (
      <div className="liveblog-pagination">
        <div>
          <button
            disabled={page === 1}
            className="button button-large liveblog-btn liveblog-pagination-btn liveblog-pagination-first"
            onClick={(e) => { e.preventDefault(); getEntriesPaginated(1, 'first'); } }
          >
            First
          </button>
          <button
            disabled={page === 1}
            className="button button-large liveblog-btn liveblog-pagination-btn liveblog-pagination-prev"
            onClick={(e) => { e.preventDefault(); getEntriesPaginated((page - 1), 'last'); } }
          >
            Prev
          </button>
        </div>
        <span className="liveblog-pagination-pages">{pageNavigation}
        </span>
        <div>
          <button
            disabled={page === pages}
            className="button button-large liveblog-btn liveblog-pagination-btn liveblog-pagination-next"
            onClick={(e) => { e.preventDefault(); getEntriesPaginated((page + 1), 'first'); } }
          >
            Next
          </button>
          <button
            disabled={page === pages}
            className="button button-large liveblog-btn liveblog-pagination-btn liveblog-pagination-last"
            onClick={(e) => { e.preventDefault(); getEntriesPaginated(pages, 'first'); } }
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
