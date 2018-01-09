import PropTypes from 'prop-types';
import React, { Component } from 'react';
import Modal from '../components/Modal';
import Loader from '../components/Loader';
import { getMedia } from '../services/api';

const getImageSrc = (image) => {
  if (image.media_details.sizes.medium) {
    return image.media_details.sizes.medium.source_url;
  }
  return image.media_details.sizes.full.source_url;
};

const Pagination = ({ totalPages, currentPage, goToPage, images }) => {
  const PAGINATION_SPREAD = 4;
  const pages = [];

  for (let i = currentPage; i <= currentPage + PAGINATION_SPREAD; i += 1) {
    if (i === totalPages) break;
    if (i >= 2) pages.push(i);
  }

  if (images.length === 0) {
    return false;
  }

  return (
    <ul className="liveblog-media-pagination">
      <Page page={1} goToPage={() => goToPage(1)} currentPage={currentPage} />
      {
        currentPage !== 1 && <span className="liveblog-pagination-dots">...</span>
      }
      {
        pages.map(page =>
          <Page
            key={page}
            onClick={() => goToPage(page)}
            page={page}
            currentPage={currentPage}
          />,
        )
      }
      <span className="liveblog-pagination-dots">...</span>
      <Page page={totalPages} goToPage={() => goToPage(totalPages)} currentPage={currentPage} />
    </ul>
  );
};

Pagination.propTypes = {
  totalPages: PropTypes.number,
  currentPage: PropTypes.number,
  goToPage: PropTypes.func,
  images: PropTypes.array,
};

const Page = ({ page, currentPage, goToPage }) => (
  <li
    className={`liveblog-media-pagination-number ${currentPage === page ? 'is-active' : ''}`}
    onClick={() => goToPage(page)}
  >
    {page}
  </li>
);

Page.propTypes = {
  page: PropTypes.number,
  goToPage: PropTypes.func,
  currentPage: PropTypes.any,
};

class MediaLibrary extends Component {
  constructor(props) {
    super(props);
    this.defaultParams = {
      per_page: 24,
    };

    this.state = {
      loading: true,
      images: [],
      currentPage: 0,
      totalPages: 0,
      searchInput: '',
    };
  }

  componentDidUpdate(prevProps) {
    const { active } = this.props;
    if (prevProps.active === active) return;

    this.getMedia({ page: 1 });
  }

  getMedia(params = {}) {
    const { searchInput } = this.state;

    const mergedParams = {
      ...this.defaultParams,
      ...params,
    };

    if (searchInput) {
      mergedParams.search = searchInput;
    }

    this.setState({ loading: true });

    getMedia(mergedParams)
      .timeout(10000)
      .map(res => ({
        res: res.response,
        pages: res.xhr.getResponseHeader('X-WP-TotalPages'),
      }))
      .subscribe(({ res, pages }) => {
        this.setState({
          images: res,
          totalPages: pages,
          currentPage: mergedParams.page,
          loading: false,
        });
      });
  }

  prev() {
    const { currentPage } = this.state;
    if (currentPage <= 1) return;
    const page = currentPage - 1;

    this.getMedia({ page });
  }

  next() {
    const { currentPage, totalPages } = this.state;
    if (currentPage >= totalPages) return;
    const page = currentPage + 1;
    this.getMedia({ page });
  }

  goToPage(page) {
    const { currentPage } = this.state;
    if (currentPage === page) return;
    this.getMedia({ page });
  }

  handleSearch(event) {
    event.preventDefault();
    this.getMedia({ page: 1 });
  }

  renderImages() {
    const { insertImage } = this.props;
    const { loading, images } = this.state;

    if (loading) {
      return (
        <div className="liveblog-placeholder liveblog-media-loading">
          <div className="liveblog-placeholder-inner">
            <Loader />
            <div className="liveblog-placeholder-text">Loading Images...</div>
          </div>
        </div>
      );
    }

    if (images.length === 0) {
      return (
        <div className="liveblog-placeholder liveblog-media-loading">
          No images found.
        </div>
      );
    }

    return (
      <ul className="liveblog-media-grid">
        {
          images.map(image => (
            <li
              key={image.id}
              onClick={() => insertImage(image.media_details.sizes.full.source_url)}
              className="liveblog-media-grid-item"
            >
              <img src={getImageSrc(image)} />
            </li>
          ))
        }
      </ul>
    );
  }

  render() {
    const { active, close } = this.props;
    const { currentPage, totalPages, searchInput } = this.state;

    return (
      <Modal active={active}>
        <span onClick={close} className="dashicons dashicons-no liveblog-modal-close"></span>
        <h1 className="liveblog-editor-title">Add Media</h1>
        <form onSubmit={this.handleSearch.bind(this)}>
          <input
            value={searchInput}
            onChange={event => this.setState({ searchInput: event.target.value })}
          />
        </form>
        {this.renderImages()}
        <div className="liveblog-media-controls">
          <button className="liveblog-btn" onClick={this.prev.bind(this)}>
            Prev
          </button>
          <Pagination {...this.state} />
          <button className="liveblog-btn" onClick={this.next.bind(this)}>
            Next
          </button>
        </div>
      </Modal>
    );
  }
}

MediaLibrary.propTypes = {
  active: PropTypes.bool,
  insertImage: PropTypes.func,
  close: PropTypes.func,
};

export default MediaLibrary;
