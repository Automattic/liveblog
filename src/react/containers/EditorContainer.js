/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import { EditorState, ContentState, convertFromHTML } from 'draft-js';
import { stateToHTML } from 'draft-js-export-html';
import Editor, { composeDecorators } from 'draft-js-plugins-editor';
import createEmojiPlugin from 'draft-js-emoji-plugin';
import createMentionPlugin from 'draft-js-mention-plugin';
import createImagePlugin from 'draft-js-image-plugin';
import createFocusPlugin from 'draft-js-focus-plugin';
import createBlockDndPlugin from 'draft-js-drag-n-drop-plugin';

import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

import { getAuthors } from '../services/api';

import EditorToolbarContainer, { Link, findLinkEntities } from './EditorToolbarContainer';
import Button from '../components/Button';

const AuthorComponent = ({ mention, theme, searchValue, ...parentProps }) => (
  <div {...parentProps}>
    <div className="liveblog-popover-item">
      <div
        className="liveblog-popover-item-figure"
        dangerouslySetInnerHTML={{ __html: mention.get('avatar') }}
      />

      <div className="liveblog-popover-item-figure">
        {mention.get('key')}
      </div>
    </div>
  </div>
);

class EditorContainer extends Component {
  constructor(props) {
    super(props);

    this.emojiPlugin = createEmojiPlugin({
      useNativeArt: true,
      positionSuggestions: () => ({}),
      theme: {
        emojiSuggestions: 'liveblog-popover',
        emojiSuggestionsEntry: 'liveblog-popover-item',
        emojiSuggestionsEntryFocused: 'liveblog-popover-item--focused',
        emojiSuggestionsEntryIcon: 'liveblog-popover-item-figure',
        emojiSuggestionsEntryText: 'liveblog-popover-item-body',
      },
    });

    this.mentionPlugin = createMentionPlugin({
      positionSuggestions: () => ({}),
      entityMutability: 'IMMUTABLE',
      mentionPrefix: '@',
      theme: {
        mentionSuggestions: 'liveblog-popover',
        mentionSuggestionsEntryFocused: 'liveblog-popover-item--focused',
      },
      mentionComponent: ({ mention }) => (
        <a href="#">{mention.get('key')}</a>
      ),
    });

    this.focusPlugin = createFocusPlugin();
    this.blockDndPlugin = createBlockDndPlugin();

    this.imagePlugin = createImagePlugin({
      decorator: composeDecorators(
        this.focusPlugin.decorator,
        this.blockDndPlugin.decorator,
      ),
    });

    this.customDecorators = [{
      strategy: findLinkEntities,
      component: Link,
    }];

    const editorState = props.entry
      ? EditorState.createWithContent(
        ContentState.createFromBlockArray(
          convertFromHTML(props.entry.render),
        ),
      )
      : EditorState.createEmpty();

    this.state = {
      editorState,
      authors: [],
    };
  }

  onChange(editorState) {
    this.setState({
      editorState,
    });
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

  updateAuthors(payload) {
    const { config } = this.props;

    getAuthors(payload, config)
      .timeout(10000)
      .map(res => res.response)
      .subscribe(res => this.setState({ authors: res }));
  }

  render() {
    const { editorState, authors } = this.state;
    const { isEditing } = this.props;
    const { EmojiSuggestions } = this.emojiPlugin;
    const { MentionSuggestions } = this.mentionPlugin;

    const plugins = [
      this.emojiPlugin,
      this.mentionPlugin,
      this.blockDndPlugin,
      this.focusPlugin,
      this.imagePlugin,
    ];

    return (
      <div className={`liveblog-editor-container ${isEditing ? 'liveblog-editor-container--edit' : ''}`}>
        {!isEditing && <h1>Add New Entry</h1>}
        <div style={{ position: 'relative' }}>
          <EditorToolbarContainer
            editorState={editorState}
            onChange={this.onChange.bind(this)}
          />
          <Editor
            editorState={this.state.editorState}
            onChange={this.onChange.bind(this)}
            decorators={this.customDecorators}
            plugins={plugins}
            ref={ref => this.editor = ref}
          />
          <EmojiSuggestions />
          <MentionSuggestions
            onSearchChange={({ value }) => this.updateAuthors(value)}
            suggestions={authors}
            entryComponent={AuthorComponent}
          />
        </div>
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
