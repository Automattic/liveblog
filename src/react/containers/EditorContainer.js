/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
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

    // Handle empty text - return empty options immediately
    if (!text || text.trim() === '') {
      return Promise.resolve({ options: [] });
    }

    return new Promise((resolve) => {
      getAuthors(text, config)
        .pipe(
          timeout(10000),
          map(res => res.response),
        )
        .subscribe({
          next: res => resolve({ options: res || [] }),
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
      <div className="liveblog-editor-container">
        {!isEditing && <h1 className="liveblog-editor-title">Add New Entry</h1>}
        <div className="liveblog-editor-tabs">
          <button
            className={`liveblog-editor-tab ${mode === 'editor' ? 'is-active' : ''}`}
            onClick={() => this.setState({ mode: 'editor' })}
          >
            Visual
          </button>
          <button
            className={`liveblog-editor-tab ${mode === 'raw' ? 'is-active' : ''}`}
            onClick={() => this.setState({ mode: 'raw' })}
          >
              Text
          </button>
          <button
            className={`liveblog-editor-tab ${mode === 'preview' ? 'is-active' : ''}`}
            onClick={() => this.setState({ mode: 'preview' })}
          >
              Preview
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
          <React.Suspense fallback={<div className="liveblog-editor-loading">Loading editor...</div>}>
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
        <h2 className="liveblog-editor-subTitle">Authors:</h2>
        <Async
          isMulti={true}
          value={authors}
          getOptionValue={(option) => option.key}
          getOptionLabel={(option) => option.name}
          onChange={this.onSelectAuthorChange.bind(this)}
          components={{ Option: AuthorSelectOption }}
          loadOptions={this.getUsers.bind(this)}
          isClearable={false}
          cacheOptions={false}
        />
        <button className="liveblog-btn liveblog-publish-btn" onClick={this.publish.bind(this)}>
          {isEditing ? 'Publish Update' : 'Publish New Entry'}
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
