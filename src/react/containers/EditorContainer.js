/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import { EditorState, ContentState, convertFromHTML } from 'draft-js';
import { stateToHTML } from 'draft-js-export-html';

import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

import { getAuthors, getHashtags } from '../services/api';

import Button from '../components/Button';

import Editor, { decorators } from '../Editor/Editor';

class EditorContainer extends Component {
  constructor(props) {
    super(props);

    const initialEditorState = props.entry
      ? EditorState.createWithContent(
        ContentState.createFromBlockArray(
          convertFromHTML(props.entry.content),
        ),
        decorators,
      )
      : EditorState.createEmpty(decorators);

    this.state = {
      editorState: initialEditorState,
      suggestions: [],
    };

    this.onChange = editorState => this.setState({ editorState });
  }

  publish() {
    const { updateEntry, entry, entryEditClose, createEntry, isEditing } = this.props;
    const { editorState } = this.state;
    const content = stateToHTML(editorState.getCurrentContent());

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

    this.setState({ editorState: newEditorState });
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

  render() {
    const { editorState, suggestions } = this.state;
    const { isEditing, config } = this.props;

    return (
      <div className="liveblog-editor-container">
        {!isEditing && <h1>Add New Entry</h1>}
        <Editor
          editorState={editorState}
          onChange={this.onChange}
          suggestions={suggestions}
          // Need to work out a better way of handling this.
          resetSuggestions={() => this.setState({ suggestions: [] })}
          onSearch={(trigger, text) => this.handleOnSearch(trigger, text)}
          autocompleteConfig={config.autocomplete}
        />
        <Button type="primary" modifiers="wide" click={this.publish.bind(this)}>
          {isEditing ? 'Publish Update' : 'Publish New Entry'}
        </Button>
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
