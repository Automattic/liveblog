/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import { __ } from '@wordpress/i18n';
import { AsyncPaginate as Async } from 'react-select-async-paginate';
import { html } from 'js-beautify';
import { timeout, map } from 'rxjs/operators';

import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

import { getAuthors, getHashtags, uploadImage } from '../services/api';

import PreviewContainer from './PreviewContainer';
import AuthorSelectOption from '../components/AuthorSelectOption';
import HTMLInput from '../components/HTMLInput';

import { getImageSize } from '../Editor/utils';

// Lazy load LexicalEditor for code splitting
const LexicalEditor = React.lazy( () => import( '../Editor/LexicalEditor' ) );

class EditorContainer extends Component {
  constructor(props) {
    super(props);

    const initialAuthors = props.entry
      ? props.entry.authors
      : [props.config.current_user];

    this.state = {
      suggestions: [],
      authors: initialAuthors,
      mode: 'editor',
      readOnly: false,
      rawText: props.entry ? props.entry.content : '',
      previewKey: 0,
      // Store the HTML content for the editor
      editorContent: props.entry ? props.entry.content : '',
    };
  }

  setReadOnly(state) {
    this.setState({
      readOnly: state,
    });
  }

  getContent() {
    return this.state.editorContent;
  }

  /**
   * Handle content changes from the Lexical editor.
   *
   * @param {string} htmlContent - The updated HTML content.
   */
  onEditorChange(htmlContent) {
    this.setState({
      editorContent: htmlContent,
      rawText: html(htmlContent),
    });
  }

  /**
   * Sync raw HTML text input back to editor content.
   */
  syncRawTextToEditorContent() {
    this.setState(prevState => ({
      editorContent: prevState.rawText,
      // Increment previewKey to force LexicalEditor to remount with new content
      previewKey: prevState.previewKey + 1,
    }));
  }

  /**
   * Handle keyboard shortcuts.
   *
   * @param {KeyboardEvent} event - The keyboard event.
   */
  handleKeyDown(event) {
    // Ctrl+Enter (Windows/Linux) or Cmd+Enter (Mac) to publish
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
      event.preventDefault();
      this.publish();
    }
  }

  publish() {
    const { updateEntry, entry, entryEditClose, createEntry, isEditing } = this.props;
    const { authors, editorContent } = this.state;
    const content = this.getContent();
    const authorIds = authors.map(author => author.id);
    const author = authorIds.length > 0 ? authorIds[0] : false;
    const contributors = authorIds.length > 1 ? authorIds.slice(1, authorIds.length) : false;
    const htmlregex = /<(img|picture|video|audio|canvas|svg|iframe|embed) ?.*>/;

    // We don't want an editor publishing empty entries
    // So we must check if there is any text within the editor
    // If we fail to find text then we should check for a valid
    // list of html elements, mainly visual for example images.
    const textContent = editorContent.replace(/<[^>]*>/g, '').trim();
    if (!textContent && htmlregex.exec(editorContent) === null) {
      return;
    }

    if (isEditing) {
      updateEntry({
        id: entry.id,
        content,
        author,
        contributors,
      });
      entryEditClose(entry.id);
      return;
    }

    createEntry({
      content,
      author,
      contributors,
    });

    // Clear editor content by incrementing previewKey to force remount
    this.setState(prevState => ({
      editorContent: '',
      rawText: '',
      readOnly: false,
      previewKey: prevState.previewKey + 1,
    }));
  }

  onSelectAuthorChange(value) {
    this.setState({
      authors: value,
    });
  }

  getUsers(text) {
    const { config } = this.props;
    const searchTerm = text || '';
    const isDefaultLoad = searchTerm.trim() === '';

    return new Promise((resolve) => {
      getAuthors(searchTerm, config)
        .pipe(
          timeout(10000),
          map(res => res.response),
        )
        .subscribe({
          next: res => {
            let options = res || [];

            // Limit default results and add hint if there are more
            if (isDefaultLoad && options.length > 10) {
              options = options.slice(0, 10);
              options.push({
                id: '__hint__',
                key: '__hint__',
                name: __( 'Type to search for more authors…', 'liveblog' ),
                isDisabled: true,
                isHint: true,
              });
            }

            resolve({ options });
          },
          error: err => {
            // Fail gracefully with empty options on error (e.g., 401)
            console.warn('Authors API error:', err);
            resolve({ options: [] });
          },
        });
    });
  }

  getAuthorsForAutocomplete(text) {
    const { config } = this.props;
    getAuthors(text, config)
      .pipe(
        timeout(10000),
        map(res => res.response),
      )
      .subscribe(res => this.setState({
        suggestions: res.map(author => author),
      }));
  }

  getHashtags(text) {
    const { config } = this.props;
    getHashtags(text, config)
      .pipe(
        timeout(10000),
        map(res => res.response),
      )
      .subscribe(res => this.setState({
        suggestions: res.map(hashtag => hashtag),
      }));
  }

  filterCommandSuggestions(suggestions, filter) {
    this.setState({
      suggestions: suggestions.filter(item =>
        item.substring(0, filter.length) === filter,
      ),
    });
  }

  filterEmojiSuggestions(suggestions, filter) {
    this.setState({
      suggestions: suggestions.filter(item =>
        item.key.toString().substring(0, filter.length) === filter,
      ),
    });
  }

  handleOnSearch(trigger, text) {
    const { config } = this.props;

    switch (trigger) {
      case '@':
        this.getAuthorsForAutocomplete(text);
        break;
      case '#':
        this.getHashtags(text);
        break;
      case '/':
        this.filterCommandSuggestions(config.autocomplete[0].data, text);
        break;
      case ':':
        this.filterEmojiSuggestions(config.autocomplete[1].data, text);
        break;
      default:
        this.setState({ suggestions: [] });
        break;
    }
  }

  handleImageUpload(file) {
    const { config } = this.props;

    const formData = new FormData();
    formData.append('name', file.name);
    formData.append('action', 'upload-attachment');
    formData.append('_wpnonce', config.image_nonce);
    formData.append('async-upload', file);

    return new Promise((resolve, reject) => {
      uploadImage(formData)
        .pipe(
          timeout(60000),
          map(res => res.response),
        )
        .subscribe({
          next: (res) => {
            const src = getImageSize(res.data.sizes, config.default_image_size);
            resolve(src);
          },
          error: err => reject(err),
        });
    });
  }

  render() {
    const {
      suggestions,
      mode,
      authors,
      readOnly,
      previewKey,
    } = this.state;

    const { isEditing, config } = this.props;

    return (
      <div className="liveblog-editor-container" onKeyDown={this.handleKeyDown.bind(this)}>
        {!isEditing && <h1 className="liveblog-editor-title">{ __( 'Add New Entry', 'liveblog' ) }</h1>}
        <div className="liveblog-editor-tabs">
          <button
            className={`liveblog-editor-tab ${mode === 'editor' ? 'is-active' : ''}`}
            onClick={() => this.setState({ mode: 'editor' })}
          >
            { __( 'Visual', 'liveblog' ) }
          </button>
          <button
            className={`liveblog-editor-tab ${mode === 'raw' ? 'is-active' : ''}`}
            onClick={() => this.setState({ mode: 'raw' })}
          >
            { __( 'Text', 'liveblog' ) }
          </button>
          <button
            className={`liveblog-editor-tab ${mode === 'preview' ? 'is-active' : ''}`}
            onClick={() => this.setState({ mode: 'preview' })}
          >
            { __( 'Preview', 'liveblog' ) }
          </button>
        </div>
        {
          mode === 'preview' &&
          <PreviewContainer
            key={previewKey}
            config={config}
            getEntryContent={() => this.getContent()}
          />
        }
        {
          mode === 'editor' &&
          <React.Suspense fallback={<div className="liveblog-editor-loading">{ __( 'Loading editor…', 'liveblog' ) }</div>}>
            <LexicalEditor
              key={previewKey}
              initialContent={this.state.editorContent}
              onChange={this.onEditorChange.bind(this)}
              readOnly={readOnly}
              suggestions={suggestions}
              onSearch={(trigger, text) => this.handleOnSearch(trigger, text)}
              handleImageUpload={this.handleImageUpload.bind(this)}
            />
          </React.Suspense>
        }
        {
          mode === 'raw' &&
          <HTMLInput
            value={this.state.rawText}
            onChange={(text) => {
              this.setState({ rawText: text }, () => {
                this.syncRawTextToEditorContent();
              });
            }}
            height="275px"
            width="100%"
          />
        }
        <h2 className="liveblog-editor-subTitle">{ __( 'Authors:', 'liveblog' ) }</h2>
        <Async
          classNamePrefix="liveblog-select"
          aria-label={__( 'Entry authors', 'liveblog' )}
          isMulti={true}
          value={authors}
          getOptionValue={(option) => option.key}
          getOptionLabel={(option) => option.name}
          onChange={this.onSelectAuthorChange.bind(this)}
          components={{ Option: AuthorSelectOption }}
          loadOptions={this.getUsers.bind(this)}
          defaultOptions={true}
          isClearable={false}
          cacheOptions={false}
          isOptionDisabled={(option) => option.isDisabled}
          noOptionsMessage={({ inputValue }) =>
            inputValue ? __( 'No authors matched', 'liveblog' ) : __( 'Loading authors…', 'liveblog' )
          }
        />
        <button className="liveblog-btn liveblog-publish-btn" onClick={this.publish.bind(this)}>
          {isEditing ? __( 'Publish Update', 'liveblog' ) : __( 'Publish New Entry', 'liveblog' )}
        </button>
      </div>
    );
  }
}

EditorContainer.propTypes = {
  config: PropTypes.object,
  updateEntry: PropTypes.func,
  entry: PropTypes.object,
  entryEditClose: PropTypes.func,
  createEntry: PropTypes.func,
  isEditing: PropTypes.bool,
  authors: PropTypes.array,
  getAuthors: PropTypes.func,
};

const mapStateToProps = state => state;

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions },
  dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EditorContainer);
