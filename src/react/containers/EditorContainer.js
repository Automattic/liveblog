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

    // Default to no authors for new entries (authorless/anonymous style).
    // Users can add authors when attribution is relevant.
    // For editing, preserve the existing authors.
    const initialAuthors = props.entry
      ? props.entry.authors
      : [];

    // Get entry content for editing, stripping /key command since checkbox handles it
    const rawEntryContent = props.entry ? props.entry.content : '';
    const entryContent = this.stripKeyCommand(rawEntryContent);

    // Check if entry is a key event:
    // 1. First check the key_event property from PHP (based on meta)
    // 2. Fall back to checking if /key exists in content (for old entries without meta)
    const hasKeyEventMeta = props.entry ? props.entry.key_event : false;
    const hasKeyInContent = this.hasKeyCommand(rawEntryContent);
    const isKeyEvent = hasKeyEventMeta || hasKeyInContent;

    this.state = {
      suggestions: [],
      authors: initialAuthors,
      mode: 'editor',
      readOnly: false,
      rawText: entryContent,
      previewKey: 0,
      // Store the HTML content for the editor (with /key stripped)
      editorContent: entryContent,
      // Key event checkbox state - synced with key_event meta or /key in content
      isKeyEvent: isKeyEvent,
      // Track if entry was originally a key event (for save logic)
      wasOriginallyKeyEvent: isKeyEvent,
    };
  }

  /**
   * Check if content contains the /key command in any form.
   *
   * @param {string} content - The content to check.
   * @returns {boolean} True if /key command is present.
   */
  hasKeyCommand(content) {
    if (!content) return false;
    // Check for /key at start of content or after non-word character
    const hasPlainKey = /(^|[^\w])\/key([^\w]|$)/i.test(content);
    // Check for transformed span version
    const hasSpanKey = /<span[^>]*class="liveblog-command[^"]*type-key"[^>]*>/i.test(content);
    return hasPlainKey || hasSpanKey;
  }

  /**
   * Strip /key command from content for display in editor.
   * The checkbox handles key event status, so we hide the command.
   *
   * @param {string} content - The content to process.
   * @returns {string} Content with /key command removed.
   */
  stripKeyCommand(content) {
    if (!content) return '';

    let processed = content;

    // Remove /key when it's inside a paragraph (with optional br): <p>/key</p> or <p>/key<br></p>
    processed = processed.replace(/<p[^>]*>\s*\/key\s*(<br\s*\/?>)?\s*<\/p>\s*/gi, '');

    // Remove transformed span version (entire span element with optional trailing whitespace/br)
    processed = processed.replace(/<span[^>]*class="liveblog-command[^"]*type-key"[^>]*>[^<]*<\/span>[\s\n]*/gi, '');

    // Remove /key at the start of content (with optional trailing whitespace/br)
    processed = processed.replace(/^\/key[\s\n]*(<br\s*\/?>[\s\n]*)*/i, '');

    // Remove /key after HTML tags (e.g., after <p> or >)
    processed = processed.replace(/(>)\s*\/key[\s\n]*(<br\s*\/?>[\s\n]*)*/gi, '$1');

    // Clean up empty paragraphs (including those with just <br>)
    processed = processed.replace(/<p[^>]*>\s*(<br\s*\/?>)?\s*<\/p>\s*/gi, '');

    // Clean up leading <br> tags
    processed = processed.replace(/^[\s\n]*(<br\s*\/?>[\s\n]*)+/gi, '');

    return processed.trim();
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

  /**
   * Process content to add or remove /key command based on checkbox state.
   *
   * Logic:
   * - If checkbox is checked: ensure /key is in content (add if missing)
   * - If checkbox is unchecked AND entry was originally a key event: strip /key
   * - If checkbox is unchecked AND entry was NOT originally a key event: preserve /key
   *   (user manually typed it for backward compatibility)
   *
   * @param {string} content - The entry content.
   * @param {boolean} isKeyEvent - Whether the key event checkbox is checked.
   * @returns {string} The processed content.
   */
  processKeyEventContent(content, isKeyEvent) {
    const hasKey = this.hasKeyCommand(content);
    const { wasOriginallyKeyEvent } = this.state;

    if (isKeyEvent) {
      // Checkbox is checked - ensure /key is in content
      if (hasKey) {
        // Already has /key (manually typed), keep as-is
        return content;
      }
      // Add /key at the beginning
      return '/key ' + content;
    }

    // Checkbox is unchecked
    // Only strip /key if the entry WAS originally a key event
    // (meaning we stripped /key on load and user explicitly unchecked)
    // If entry was NOT originally a key event, preserve any /key user typed
    if (hasKey && wasOriginallyKeyEvent) {
      return this.stripKeyCommand(content);
    }

    // Return content as-is (preserves manually typed /key)
    return content;
  }

  publish() {
    const { updateEntry, entry, entryEditClose, createEntry, isEditing } = this.props;
    const { authors, editorContent, isKeyEvent } = this.state;
    let content = this.getContent();
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

    // Process /key command based on checkbox state
    content = this.processKeyEventContent(content, isKeyEvent);

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
      isKeyEvent: false,
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
      isKeyEvent,
    } = this.state;

    const { isEditing, config } = this.props;

    return (
      <div className="liveblog-editor-container" onKeyDown={this.handleKeyDown.bind(this)}>
        {!isEditing && <h1 className="liveblog-editor-title">{ __( 'Add New Entry', 'liveblog' ) }</h1>}
        <div className="liveblog-editor-authors">
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
            isClearable={true}
            cacheOptions={false}
            isOptionDisabled={(option) => option.isDisabled}
            noOptionsMessage={({ inputValue }) =>
              inputValue ? __( 'No authors matched', 'liveblog' ) : __( 'Loading authors…', 'liveblog' )
            }
          />
        </div>
        <div className="liveblog-editor-key-event">
          <label>
            <input
              type="checkbox"
              checked={isKeyEvent}
              onChange={(e) => this.setState({ isKeyEvent: e.target.checked })}
            />
            { __( 'Key Event', 'liveblog' ) }
          </label>
        </div>
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
