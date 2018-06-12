import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class PaginationContainer extends Component {
  render() {
    const { page, pages, getEntriesPaginated, paginationType } = this.props;

    if (paginationType === 'loadMore') {
      return (
        <div className="liveblog-pagination">
          {page !== pages &&
          <button
            className="button button-large liveblog-btn liveblog-pagination-btn liveblog-pagination-loadmore"
            onClick={(e) => {
              e.preventDefault();
              getEntriesPaginated(page + 1);
            }}
          >
              Load More
          </button>}
        </div>
      );
    }

    // Don't diplsay pagination if we only have a single page.
    if (pages === 1) {
      return null;
    }

    const onClick = (e) => {
      e.preventDefault();
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

    const firstButtonClassNames = ['button', 'button-large', 'liveblog-btn', 'liveblog-pagination-btn', 'liveblog-pagination-first'].join(' ');
    const prevButtonClassNames = ['button', 'button-large', 'liveblog-btn', 'liveblog-pagination-btn', 'liveblog-pagination-prev'].join(' ');
    const nextButtonClassNames = ['button', 'button-large', 'liveblog-btn', 'liveblog-pagination-btn', 'liveblog-pagination-next'].join(' ');
    const lastButtonClassNames = ['button', 'button-large', 'liveblog-btn', 'liveblog-pagination-btn', 'liveblog-pagination-last'].join(' ');

    return (
      <div className="liveblog-pagination">
        <div>
          <button
            disabled={page === 1}
            className={firstButtonClassNames}
            onClick={(e) => { e.preventDefault(); getEntriesPaginated(1, 'first'); } }
          >
            First
          </button>
          <button
            disabled={page === 1}
            className={prevButtonClassNames}
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
            className={nextButtonClassNames}
            onClick={(e) => { e.preventDefault(); getEntriesPaginated((page + 1), 'first'); } }
          >
            Next
          </button>
          <button
            disabled={page === pages}
            className={lastButtonClassNames}
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
  paginationType: PropTypes.string,
};

const mapStateToProps = state => ({
  page: state.pagination.page,
  pages: state.pagination.pages,
  paginationType: state.config.paginationType,
});

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(PaginationContainer);
