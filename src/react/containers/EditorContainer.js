/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import { Async } from 'react-select';
import 'react-select/dist/react-select.css';
import { html } from 'js-beautify';
import { debounce } from 'lodash-es';

import { EditorState, ContentState } from 'draft-js';

import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

import { getAuthors, uploadImage } from '../services/api';

import PreviewContainer from './PreviewContainer';
import AuthorSelectOption from '../components/AuthorSelectOption';
import HTMLInput from '../components/HTMLInput';
import PostHeadline from '../components/PostHeadline';

import Editor, { decorators, convertFromHTML, convertToHTML } from '../Editor/index';

import { getImageSize } from '../Editor/utils';

class EditorContainer extends Component {
  constructor(props) {
    super(props);

    let initialEditorState;
    let initialAuthors;

    if (props.entry) {
      initialEditorState = EditorState.createWithContent(
        convertFromHTML(props.entry.content, {
          setReadOnly: this.setReadOnly.bind(this),
          handleImageUpload: this.handleImageUpload.bind(this),
          defaultImageSize: props.config.default_image_size,
        }),
        decorators,
      );
      initialAuthors = props.entry.authors;
    } else {
      initialEditorState = EditorState.createEmpty(decorators);
      initialAuthors = (props.config.prefill_author_field === '1') ? [props.config.current_user] : [];
    }

    this.state = {
      editorState: initialEditorState,
      suggestions: [],
      authors: initialAuthors,
      mode: 'editor',
      readOnly: false,
      headline: props.entry ? props.entry.headline : '',
      rawText: props.entry ? props.entry.content : '',
      canPublish: false,
      error: false,
      errorMessage: '',
      status: props.entry ? props.entry.status : 'draft',
    };

    this.onChange = editorState => this.setState({
      editorState,
      rawText: html(convertToHTML(editorState.getCurrentContent())),
    });

    this.clearAuthors = () => this.setState({
      authors: false,
    });

    this.clearHeadline = () => this.setState({
      headline: '',
    });

    this.setEnablePosting = state => this.setState({
      canPublish: state,
    });

    this.close = (event) => {
      event.preventDefault();
      this.props.entryEditClose(this.props.entry.id);
    };

    this.setError = (error, errorMessage) => this.setState({
      error,
      errorMessage,
    });

    this.getUsers = debounce(this.getUsers.bind(this), props.config.author_list_debounce_time);
  }

  setReadOnly(state) {
    this.setState({
      readOnly: state,
    });
  }

  getContent() {
    const { editorState } = this.state;
    return editorState.rawText;
  }

  syncRawTextToEditorState() {
    this.setState({
      editorState:
        EditorState.createWithContent(
          convertFromHTML(this.state.rawText, {
            setReadOnly: this.setReadOnly.bind(this),
            handleImageUpload: this.handleImageUpload.bind(this),
            defaultImageSize: this.props.config.default_image_size,
          }),
          decorators,
        ),
    });
  }

  publish(status) {
    const { updateEntry, entry, createEntry, isEditing } = this.props;
    const { editorState, authors } = this.state;
    const content = this.getContent();
    const authorIds = authors ? authors.map(author => author.id) : [];
    const headline = this.state.headline;

    if (isEditing) {
      updateEntry({
        id: entry.id,
        content,
        authors,
        authorIds,
        headline,
        status,
      });
      return;
    }

    createEntry({
      content,
      authors,
      authorIds,
      headline,
      status,
    });

    // Prevent publish empty posts when creating new editor.
    this.setEnablePosting(false);

    const newEditorState = EditorState.push(
      editorState,
      ContentState.createFromText(''),
    );

    this.onChange(newEditorState);
    this.setState({ readOnly: false });
  }

  onSelectAuthorChange(value) {
    this.setState({
      authors: value,
    });
  }

  onHeadlineChange(value) {
    this.setState({
      headline: value,
    });
  }

  getUsers(text, callback) {
    const { config } = this.props;
    getAuthors(text, config)
      .timeout(20000)
      .map(res => res.response)
      .subscribe(res => callback(null, {
        options: res,
        complete: false,
      }));
  }

  getAuthors(text) {
    const { config } = this.props;
    getAuthors(text, config)
      .timeout(10000)
      .map(res => res.response)
      .subscribe(res => this.setState({
        suggestions: res.map(author => author),
      }));
  }

  filterCommandSuggestions(suggestions, filter) {
    this.setState({
      suggestions: suggestions.filter(item =>
        item.substring(0, filter.length) === filter,
      ),
    });
  }

  handleOnSearch(trigger, text) {
    const { config } = this.props;

    switch (trigger) {
      case '@':
        this.getAuthors(text);
        break;
      case '/':
        this.filterCommandSuggestions(config.autocomplete[0].data, text);
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

    return new Promise((resolve) => {
      uploadImage(formData)
        .timeout(60000)
        .map(res => res.response)
        .subscribe((res) => {
          const src = getImageSize(res.data.sizes, config.default_image_size);
          resolve(src);
        });
    });
  }

  render() {
    const {
      editorState,
      suggestions,
      mode,
      authors,
      readOnly,
      headline,
      error,
      errorMessage,
      status,
    } = this.state;

    let {
      canPublish,
    } = this.state;

    const { isEditing, config } = this.props;

    const errorData = {
      error: this.props.api.error || false,
      errorMessage: this.props.api.errorMessage || '',
    };

    const authorIds = authors ?
      authors.map((author) => {
        if (author && author.id) {
          return author.id;
        }
        return false;
      }) : [];

    if (window.liveblog_settings.author_required && window.liveblog_settings.author_required === '1' && !authors.length) {
      canPublish = false;
    }

    return (
      <div className="liveblog-editor-container">
        {!isEditing && <h1 className="liveblog-editor-title">Add New Entry</h1>}
        <PostHeadline
          onChange={this.onHeadlineChange.bind(this)}
          headline={headline}
        />
        {
          mode === 'preview' &&
          <PreviewContainer
            config={config}
            getEntryContent={() => this.getContent()}
          />
        }
        {
          mode === 'editor' &&
          <Editor
            editorContainer={this}
            editorState={editorState}
            onChange={this.onChange}
            suggestions={suggestions}
            resetSuggestions={() => this.setState({ suggestions: [] })}
            onSearch={(trigger, text) => this.handleOnSearch(trigger, text)}
            autocompleteConfig={config.autocomplete}
            handleImageUpload={this.handleImageUpload.bind(this)}
            readOnly={readOnly}
            setReadOnly={this.setReadOnly.bind(this)}
            defaultImageSize={config.default_image_size}
            clearAuthors={this.clearAuthors}
            clearHeadline={this.clearHeadline}
            rawText={this.state.rawText}
            setEnablePosting={this.setEnablePosting}
            setError={this.setError}
            errorData={errorData}
          />
        }
        {
          mode === 'raw' &&
          <HTMLInput
            value={this.state.rawText}
            onChange={(text) => {
              this.setState({ rawText: text }, () => {
                this.syncRawTextToEditorState();
              });
            }}
            height="275px"
            width="100%"
          />
        }
        <h2 className="liveblog-editor-subTitle">Authors:</h2>
        <Async
          multi={true}
          value={authors}
          valueKey="key"
          labelKey="name"
          onChange={this.onSelectAuthorChange.bind(this)}
          optionComponent={AuthorSelectOption}
          loadOptions={this.getUsers}
          clearable={false}
          cache={false}
        />

        { !isEditing && <button
          disabled={ canPublish ? '' : 'disabled'}
          className="button button-secondary button-large liveblog-btn liveblog-draft-btn"
          onClick={ (event) => {
            event.preventDefault();
            this.publish('draft');
          } }>
          Save Draft
        </button> }

        <button
          disabled={ canPublish ? '' : 'disabled'}
          className="button button-primary button-large liveblog-btn liveblog-publish-btn"
          onClick={ (event) => {
            event.preventDefault();
            this.publish('publish');
          } }>
          {isEditing && 'publish' === status ? 'Update' : 'Publish'}
        </button>

        {
          isEditing && <button
            className="button button-large liveblog-btn liveblog-btn-small liveblog-close-btn"
            onClick={this.close}
          >
             Close Editor
          </button>
        }
        <span className={ `liveblog-update-fail${(error) ? '' : ' hidden'}` }>{ errorMessage }</span>
        <input type="hidden" id="liveblog_editor_authors" value={authorIds.join(',')} />
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
