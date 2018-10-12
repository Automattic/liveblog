import ScrollTrigger from 'react-scroll-trigger';
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class PaginationContainer extends Component {
  render() {
    const { page, pages, getEntriesPaginated, paginationType, isLoading, autoLoad } = this.props;

    if (paginationType === 'loadMore') {
      return (
        <span>
          <ScrollTrigger
            onEnter={() => {
              // Load the next page of entries if available.
              if (autoLoad && page !== pages) {
                getEntriesPaginated(page + 1);
              }
            } }
            onExit={() => {}}
          />

          <div className="liveblog-pagination">
            {
              (page !== pages) &&
                (
                  isLoading ?
                    <span className="liveblog-submit-spinner" /> :
                    <button
                      className="button button-large liveblog-btn
                        liveblog-pagination-btn liveblog-pagination-loadmore"
                      onClick={(e) => {
                        e.preventDefault();
                        getEntriesPaginated(page + 1);
                      }}
                    >
                    Load More
                    </button>
                )
            }
          </div>
        </span>
      );
    }

    // Don't display pagination if we only have a single page.
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
        <span className="liveblog-pagination-pages">{pageNavigation}
        </span>
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
  paginationType: PropTypes.string,
  autoLoad: PropTypes.string,
  isLoading: PropTypes.bool,
};

const mapStateToProps = state => ({
  page: state.pagination.page,
  pages: state.pagination.pages,
  paginationType: state.config.paginationType,
  autoLoad: state.config.autoLoad,
  isLoading: state.api.loading,
});

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(PaginationContainer);
