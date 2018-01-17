// @todo This file could be tidied up and split out into seperate components.
/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { getMedia } from '../../services/api';

import Button from '../Button';
import Loader from '../../components/Loader';

/**
 * Find the image thumbanil src
 * @param {object} image
 */
const getImageThumbnail = (image) => {
  if (!image) return '';
  if (image.media_details.sizes.thumbnail) {
    return image.media_details.sizes.thumbnail.source_url;
  }
  return '';
};

/**
 * Get the most suitable image size.
 * @param {object} image
 * @param {string} selectedSize
 */
const getImageSize = (image, selectedSize) => {
  if (!image) return '';
  if (image.media_details.sizes[selectedSize]) {
    return image.media_details.sizes[selectedSize].source_url;
  }
  if (image.media_details.sizes.full) {
    return image.media_details.sizes.full.source_url;
  }
  return '';
};

class Media extends Component {
  constructor(props) {
    super(props);

    // Set default params to retrieve media.
    this.defaultParams = {
      per_page: 12,
    };

    // This needs to unique per block, so we might aswell just use
    // the block's unique key.
    this.inputId = props.block.getKey();

    const { imageSizes, selectedSize } = props.getMetadata();

    let defaultSize;

    if (!selectedSize) {
      defaultSize = imageSizes.sizes.includes(imageSizes.default)
        ? imageSizes.default
        : 'full';
    } else {
      defaultSize = selectedSize;
    }

    this.imageSizes = [
      ...imageSizes.sizes,
      'full',
    ];

    this.defaultState = {
      loading: true,
      images: [],
      currentPage: 0,
      totalPages: 0,
      searchInput: '',
      searching: false,
      uploading: false,
      selectedSize: defaultSize,
    };

    this.state = {
      ...this.defaultState,
    };
  }

  /**
   * Retrieve posts when entering edit mode.
   * @param {object} prevProps
   */
  componentDidUpdate(prevProps) {
    const { edit } = this.props;
    if (edit !== prevProps.edit && edit) {
      this.setState({ searching: true });
      this.getMedia({ page: 1 });
    }
  }

  /**
   * Retrive media from api and update the state accordingly.
   * @param {object} params
   */
  getMedia(params = {}) {
    const { searchInput, images, searching } = this.state;

    // Merge default params and entered params.
    const mergedParams = {
      ...this.defaultParams,
      ...params,
    };

    // Make sure we are filtering by search if there is an input.
    if (searchInput) mergedParams.search = searchInput;

    this.setState({ loading: true });

    getMedia(mergedParams)
      .timeout(10000)
      .map(res => ({
        res: res.response,
        pages: res.xhr.getResponseHeader('X-WP-TotalPages'),
      }))
      .subscribe(({ res, pages }) => {
        // If have submitted a new search we only want to show page 1.
        let result = res;
        // Otherwise we want to merge the current images and response
        // for lazy loading.
        if (!searching) {
          result = [
            ...images,
            ...res,
          ];
        }
        this.setState({
          images: result,
          totalPages: pages,
          currentPage: mergedParams.page,
          loading: false,
          searching: false,
        });
      });
  }

  /**
   * Handle loading more button by next page of posts.
   */
  loadMore() {
    const { currentPage } = this.state;
    this.getMedia({ page: currentPage + 1 });
  }

  /**
   * Close edit mode.
   */
  cancel() {
    const { setEditMode } = this.props;
    this.setState({ ...this.defaultState });
    setEditMode(false);
  }

  /**
   * Handle selecting an image from the media library.
   */
  selectImage(src) {
    const { setEditMode, replaceMetadata } = this.props;
    setEditMode(false);
    this.setState({ ...this.defaultState });
    replaceMetadata({ image: src, edit: false });
  }

  /**
   * Handle when a search is submitted.
   */
  handleSearch(event) {
    event.preventDefault();
    this.setState({ searching: true }, () => {
      this.getMedia({ page: 1 });
    });
  }

  /**
   * Handle upload of an image.
   */
  uploadImage() {
    const { setEditMode, replaceMetadata, getMetadata } = this.props;
    const { handleImageUpload } = getMetadata();
    const files = this.imageUpload.files;
    if (files.length === 0) return;

    // Set uploading state so we can render a loader in the UI.
    this.setState({
      ...this.defaultState,
      uploading: true,
    });

    // Make sure we close edit mode correctly.
    setEditMode(false);

    // Upload image to server and render in the block.
    handleImageUpload(files[0]).then((src) => {
      replaceMetadata({ image: src });
      this.setState({ uploading: false });
    });

    // Clear input value so the same file can be upload again if user wants to.
    this.imageUpload.value = '';
  }

  /**
   * Handle Image Size change.
   * @param {object} event
   */
  handleImageSizeChange(event) {
    const size = event.target.value;
    const { replaceMetadata } = this.props;
    replaceMetadata({ selectedSize: size });
    this.setState({ selectedSize: size });
  }

  /**
   * Render the media library images.
   */
  renderMediaImages() {
    const { loading, images, currentPage, totalPages, searching, selectedSize } = this.state;

    if (searching) {
      return (
        <div className="liveblog-placeholder liveblog-media-loading">
          <div className="liveblog-placeholder-inner">
            <Loader />
            <div className="liveblog-placeholder-text">Finding Images...</div>
          </div>
        </div>
      );
    }

    if (images.length === 0) {
      return (
        <div className="liveblog-placeholder liveblog-media-loading">
          <div className="liveblog-placeholder-inner">
            <div style={{ marginBottom: '.5rem' }} className="liveblog-placeholder-text">
              No Images Found
            </div>
            <span style={{ display: 'inline-block' }} onMouseDown={e => e.preventDefault()}>
              <label
                htmlFor={this.inputId}
                className="liveblog-editor-btn liveblog-editor-action-btn"
              >
                Upload an Image?
              </label>
            </span>
          </div>
        </div>
      );
    }

    return (
      <div className="liveblog-media-grid">
        {
          images.map(image => (
            <div
              key={image.id}
              onClick={this.selectImage.bind(this, getImageSize(image, selectedSize))}
              className="liveblog-media-grid-item"
            >
              <img src={getImageThumbnail(image)} />
            </div>
          ))
        }
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          margin: '.5rem',
          width: '100%',
        }}>
          {
            ((currentPage !== totalPages) && !loading) &&
            <button
              style={{ width: '100%' }}
              className="liveblog-editor-btn liveblog-editor-action-btn"
              onClick={this.loadMore.bind(this)}
            >
              Load more
            </button>
          }
          {
            loading && <Loader />
          }
        </div>
      </div>
    );
  }

  /**
   * Render a preview of the current image associated with block.
   */
  renderCurrentImage() {
    const { uploading } = this.state;
    const { getMetadata, setEditMode } = this.props;
    const { image } = getMetadata();

    if (uploading) {
      return (
        <div className="liveblog-placeholder liveblog-media-loading">
          <div className="liveblog-placeholder-inner">
            <Loader />
            <div style={{ marginTop: '.5rem' }} className="liveblog-placeholder-text">
              Uploading...
            </div>
          </div>
        </div>
      );
    }

    if (image) {
      return (
        <div className="liveblog-media-current-container">
          <img src={image} />
        </div>
      );
    }

    return (
      <div className="liveblog-placeholder liveblog-media-loading">
        <div className="liveblog-placeholder-inner">
          <div style={{ marginBottom: '.5rem' }} className="liveblog-placeholder-text">
            No Image Selected
          </div>
          <span style={{ display: 'inline-block' }} onMouseDown={e => e.preventDefault()}>
            <button
              className="liveblog-editor-btn liveblog-editor-action-btn"
              onClick={() => setEditMode(true)}
            >
              Select an Image
            </button>
          </span>
        </div>
      </div>
    );
  }

  render() {
    const { searchInput, selectedSize } = this.state;
    const { edit, setEditMode, removeBlock, getMetadata } = this.props;
    const { image } = getMetadata();

    return (
      <div className="liveblog-block-inner liveblog-media-block">
        <input
          ref={ref => this.imageUpload = ref}
          style={{ display: 'none' }}
          type="file"
          id={this.inputId}
          onChange={this.uploadImage.bind(this)}
          accept="image/jpeg,image/gif,image/png,image/jpg"
          capture="camera"
        />
        <div className="liveblog-block-header">
          <span className="liveblog-block-title-container">
            <span className="liveblog-block-title">
              { edit ? 'Add Media' : 'Image Block' }
            </span>
          </span>
          { edit &&
            <div className="liveblog-block-select-container">
              <select
                className="liveblog-block-select"
                value={selectedSize}
                onChange={this.handleImageSizeChange.bind(this)}
              >
                {
                  this.imageSizes.map((size, i) => (
                    <option key={i} value={size}>{size}</option>
                  ))
                }
              </select>
            </div>
          }
          <div className="liveblog-editor-actions">
            <span style={{ display: 'inline-block' }} onMouseDown={e => e.preventDefault()}>
              <button
                className={`liveblog-editor-btn liveblog-editor-${!edit ? 'action' : 'cancel'}-btn`}
                onClick={!edit ? () => setEditMode(true) : this.cancel.bind(this)}
              >
                {!edit
                  ? `Select ${image ? 'A Different' : 'An'} Image`
                  : 'Cancel'
                }
              </button>
            </span>
            { edit &&
              <span style={{ display: 'inline-block' }} onMouseDown={e => e.preventDefault()}>
                <label
                  htmlFor={this.inputId}
                  className="liveblog-editor-btn liveblog-editor-action-btn">
                  Upload
                </label>
              </span>
            }
            { !edit &&
              <Button
                onMouseDown={() => removeBlock()}
                icon="no-alt"
                classes="liveblog-editor-delete"
              />
            }
          </div>
        </div>
        {
          edit
            ? (
              <div>
                <form
                  className="liveblog-media-search-container"
                  onSubmit={this.handleSearch.bind(this)}
                >
                  <span
                    style={{ color: '#333' }}
                    className="dashicons dashicons-search"
                  />
                  <input
                    placeholder="Search media items..."
                    autoFocus
                    value={searchInput}
                    onChange={event => this.setState({ searchInput: event.target.value })}
                  />
                </form>
                {this.renderMediaImages()}
              </div>
            )
            : this.renderCurrentImage()
        }
      </div>
    );
  }
}

Media.propTypes = {
  setEditMode: PropTypes.func,
  block: PropTypes.object,
  getMetadata: PropTypes.func,
  replaceMetadata: PropTypes.func,
  removeBlock: PropTypes.func,
  edit: PropTypes.bool,
};

export default Media;
