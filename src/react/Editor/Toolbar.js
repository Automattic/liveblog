import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { RichUtils } from 'draft-js';

import addLink from './modifiers/addLink';
import addAtomicBlock from './modifiers/addAtomicBlock';
import Button from './Button';

class Toolbar extends Component {
  constructor(props) {
    super(props);
    this.state = {
      showURLInput: false,
      url: '',
    };

    this.onURLChange = e => this.setState({ url: e.target.value });
  }

  toggleInlineStyle(type) {
    const { onChange } = this.props;

    onChange(
      RichUtils.toggleInlineStyle(
        this.props.editorState,
        type,
      ),
    );
  }

  toggleBlockType(type) {
    const { onChange } = this.props;

    onChange(
      RichUtils.toggleBlockType(
        this.props.editorState,
        type,
      ),
    );
  }

  openLinkModal() {
    const { showURLInput } = this.state;
    if (showURLInput) return;

    const { editorState } = this.props;
    const selection = editorState.getSelection();
    if (selection.isCollapsed()) return;

    const contentState = editorState.getCurrentContent();
    const startKey = editorState.getSelection().getStartKey();
    const startOffset = editorState.getSelection().getStartOffset();
    const blockWithLinkAtBeginning = contentState.getBlockForKey(startKey);
    const linkKey = blockWithLinkAtBeginning.getEntityAt(startOffset);
    let url = 'http://';

    if (linkKey) {
      url = contentState.getEntity(linkKey).getData().url;
    }

    this.setState({
      showURLInput: true,
      url,
    });
  }

  closeLinkModal() {
    this.setState({
      showURLInput: false,
      url: '',
    });
  }

  turnIntoLink(e) {
    e.preventDefault();

    const { url } = this.state;
    const { editorState, onChange } = this.props;

    onChange(
      addLink(editorState, url),
    );

    this.setState({ url: '', showURLInput: false });
  }

  removeAsLink(e) {
    e.preventDefault();
    const { showURLInput } = this.state;

    if (showURLInput) {
      this.setState({ url: '', showURLInput: false });
    }

    const { editorState, onChange } = this.props;

    const selection = editorState.getSelection();
    if (selection.isCollapsed()) return;

    onChange(
      RichUtils.toggleLink(editorState, selection, null),
    );
  }

  addCodeBlock(e) {
    e.preventDefault();
    const { editorState, onChange, setReadOnly } = this.props;

    onChange(
      addAtomicBlock(editorState, false, {
        code: '',
        title: 'HTML',
        edit: true,
        setReadOnly,
      }, 'code-block'),
    );
  }

  addMediaBlock(e) {
    e.preventDefault();
    const {
      editorState,
      onChange,
      setReadOnly,
      handleImageUpload,
      defaultImageSize,
    } = this.props;

    onChange(
      addAtomicBlock(editorState, false, {
        setReadOnly,
        image: '',
        edit: true,
        handleImageUpload,
        defaultImageSize,
      }, 'media'),
    );
  }

  render() {
    const { showURLInput } = this.state;
    const { readOnly } = this.props;

    return (
      <div className="liveblog-editor-toolbar-container">
        <button
          disabled={readOnly}
          onClick={this.addMediaBlock.bind(this)}
          className="liveblog-editor-btn liveblog-image-upload-btn"
        >
          <span className="dashicons dashicons-format-image"></span>
          <span className="liveblog-image-upload-btn-text">Insert Image</span>
        </button>
        <div className="liveblog-toolbar">
          <Button
            onMouseDown={() => this.toggleInlineStyle('BOLD')}
            icon="editor-bold"
            readOnly={readOnly}
          />
          <Button
            onMouseDown={() => this.toggleInlineStyle('ITALIC')}
            icon="editor-italic"
            readOnly={readOnly}
          />
          <Button
            onMouseDown={() => this.toggleInlineStyle('UNDERLINE')}
            icon="editor-underline"
            readOnly={readOnly}
          />
          <Button
            onMouseDown={() => this.toggleBlockType('ordered-list-item')}
            icon="editor-ol"
            readOnly={readOnly}
          />
          <Button
            onMouseDown={() => this.toggleBlockType('unordered-list-item')}
            icon="editor-ul"
            readOnly={readOnly}
          />
          <div style={{ position: 'relative', display: 'inline-block' }}>
            <Button
              onMouseDown={this.openLinkModal.bind(this)}
              icon="admin-links"
              readOnly={readOnly}
            />
            {
              showURLInput &&
              <div className="liveblog-editor-input-container">
                <input
                  className="liveblog-input"
                  onChange={this.onURLChange}
                  value={this.state.url}
                />
                <Button
                  onMouseDown={this.turnIntoLink.bind(this)}
                  icon="yes"
                  classes="liveblog-input-enter"
                />
                <Button
                  onMouseDown={this.closeLinkModal.bind(this)}
                  icon="no-alt"
                  classes="liveblog-input-cancel"
                />
              </div>
            }
          </div>
          <Button
            onMouseDown={this.removeAsLink.bind(this)}
            icon="editor-unlink"
            readOnly={readOnly}
          />
          <Button
            onMouseDown={this.addCodeBlock.bind(this)}
            icon="editor-code"
            readOnly={readOnly}
          />
        </div>
      </div>
    );
  }
}

Toolbar.propTypes = {
  onChange: PropTypes.func,
  editorState: PropTypes.object,
  setReadOnly: PropTypes.func,
  handleImageUpload: PropTypes.func,
  readOnly: PropTypes.bool,
  defaultImageSize: PropTypes.string,
};

export default Toolbar;
