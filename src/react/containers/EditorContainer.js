/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import { EditorState, ContentState } from 'draft-js';

import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

import { getAuthors, getHashtags, uploadImage } from '../services/api';

import PreviewContainer from './PreviewContainer';

import Editor, { decorators, convertFromHTML, convertToHTML } from '../Editor/index';

class EditorContainer extends Component {
  constructor(props) {
    super(props);

    let initialEditorState;

    if (props.entry) {
      initialEditorState = EditorState.createWithContent(
        convertFromHTML(props.entry.content, {
          setReadOnly: this.setReadOnly.bind(this),
        }),
        decorators,
      );
    } else {
      initialEditorState = EditorState.createEmpty(decorators);
    }

    this.state = {
      editorState: initialEditorState,
      suggestions: [],
      preview: false,
      readOnly: false,
    };

    this.onChange = editorState => this.setState({ editorState });
  }

  setReadOnly(state) {
    this.setState({
      readOnly: state,
    });
  }

  setPreview(state) {
    this.setState({
      preview: state,
    });
  }

  getContent() {
    const { editorState } = this.state;
    return convertToHTML(editorState.getCurrentContent());
  }

  publish() {
    const { updateEntry, entry, entryEditClose, createEntry, isEditing } = this.props;
    const { editorState } = this.state;
    const content = this.getContent();

    if (isEditing) {
      updateEntry({ id: entry.id, content });
      entryEditClose(entry.id);
      return;
    }

    createEntry({ content });

    const newEditorState = EditorState.push(
      editorState,
      ContentState.createFromText(''),
    );

    this.setState({
      editorState: newEditorState,
      readOnly: false,
    });
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

  getHashtags(text) {
    const { config } = this.props;
    getHashtags(text, config)
      .timeout(10000)
      .map(res => res.response)
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
        this.getAuthors(text);
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

    return new Promise((resolve) => {
      uploadImage(formData)
        .timeout(60000)
        .map(res => res.response)
        .subscribe(res => resolve(res.data.url));
    });
  }

  render() {
    const { editorState, suggestions, preview, readOnly } = this.state;
    const { isEditing, config } = this.props;

    return (
      <div className="liveblog-editor-container">
        {!isEditing && <h1>Add New Entry</h1>}
        <div className="liveblog-editor-tabs">
          <button
            className={`liveblog-editor-tab ${!preview ? 'is-active' : ''}`}
            onClick={this.setPreview.bind(this, false)}>Editor</button>
          <button
            className={`liveblog-editor-tab ${preview ? 'is-active' : ''}`}
            onClick={this.setPreview.bind(this, true)}>
              Preview
          </button>
        </div>
        {
          preview
            ? <PreviewContainer
              config={config}
              getEntryContent={() => this.getContent()}
            />
            : <Editor
              editorState={editorState}
              onChange={this.onChange}
              suggestions={suggestions}
              resetSuggestions={() => this.setState({ suggestions: [] })}
              onSearch={(trigger, text) => this.handleOnSearch(trigger, text)}
              autocompleteConfig={config.autocomplete}
              handleImageUpload={this.handleImageUpload.bind(this)}
              readOnly={readOnly}
              setReadOnly={this.setReadOnly.bind(this)}
            />
        }
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
