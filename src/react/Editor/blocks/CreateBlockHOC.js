/* eslint-disable react/display-name */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import { EditorState } from 'draft-js';
import removeBlock from '../modifiers/removeBlock';

const CreateBlock = (Block, editorState, onChange) => class extends Component {
  constructor() {
    super();

    this.state = {
      edit: false,
    };
  }

  /**
   * Handle whether or not the editor mode should be open by default.
   * We pass this as meta data whenever we create the block in the editor.
   */
  componentDidMount() {
    const { setReadOnly, edit } = this.getMetadata();
    if (edit) {
      this.setState({ edit: true });
      setReadOnly(true);
    }
  }

  componentWillUnmount() {
    this.setEditMode(false);
  }

  /**
   * Set edit mode to new state.
   * @param {bool} state
   */
  setEditMode(state) {
    const { setReadOnly } = this.getMetadata();
    this.setState({ edit: state });
    this.replaceMetadata({ edit: state });
    setReadOnly(state);
  }

  /**
   * Retrieve metadata saved to block.
   */
  getMetadata() {
    const { contentState, block } = this.props;
    return contentState.getEntity(block.getEntityAt(0)).getData();
  }

  /**
   * Replace metadata daved to block.
   * @param {object} data
   * @param {boolean} update whether to trigger a re-render. Should be used cautiously as can
   * cause race conditions. We generally only need to re-render when we want to keep the raw html
   * input in sync.
   */
  replaceMetadata(data, update = false) {
    const { contentState, block } = this.props;
    const newContentState = contentState.mergeEntityData(block.getEntityAt(0), data);
    if (update) {
      const newEditorState = EditorState.push(
        editorState,
        newContentState,
        'replace-metadata',
      );

      onChange(
        EditorState.forceSelection(newEditorState, newEditorState.getSelection()),
      );
    }
  }

  /**
   * Set dataTransfer data on drag. We set the text key because nothing else will
   * move the cursor which is the behavior we want. It is worth noting that in development
   * builds of React this doesn't work in IE11. You can read why here -
   * https://github.com/facebook/react/issues/5700
   */
  startDrag(e) {
    const { block } = this.props;
    e.dataTransfer.dropEffect = 'move'; // eslint-disable-line no-param-reassign
    e.dataTransfer.setData('text', `DRAFT_BLOCK:${block.getKey()}`);
  }

  /**
   * Remove block from editor.
   */
  removeBlock() {
    const { block } = this.props;

    const contentAfterRemove = removeBlock(
      editorState.getCurrentContent(),
      block.getKey(),
    );

    onChange(
      EditorState.forceSelection(
        EditorState.push(editorState, contentAfterRemove, 'remove-block'),
        contentAfterRemove.getSelectionAfter(),
      ),
    );
  }

  render() {
    const { blockProps } = this.props;
    const { edit } = this.state;

    return (
      <div
        className={`liveblog-block ${edit ? 'is-editing' : ''} ${blockProps.isFocused ? 'is-focused' : ''}`}
        draggable={true}
        onDragStart={!edit ? this.startDrag.bind(this) : e => e.preventDefault()}
      >
        <Block
          {...this.props}
          edit={edit}
          setEditMode={this.setEditMode.bind(this)}
          getMetadata={this.getMetadata.bind(this)}
          replaceMetadata={this.replaceMetadata.bind(this)}
          removeBlock={this.removeBlock.bind(this)}
        />
      </div>
    );
  }
};

export default CreateBlock;
