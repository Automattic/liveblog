import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { RichUtils } from 'draft-js';
import Button from '../components/Button';

import addLink from './modifiers/addLink';

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

  render() {
    const { imageInputId } = this.props;
    const { showURLInput } = this.state;

    return (
      <div>
        <label
          className="liveblog-btn liveblog-btn--icon liveblog-btn--secondary liveblog-image-upload-btn liveblog-btn--small"
          htmlFor={imageInputId}>
          <span className="dashicons dashicons-format-image"></span> Insert Image
        </label>
        <div className="liveblog-toolbar">
          <Button modifiers="icon" onMouseDown={() => this.toggleBlockType('header-one')}>
            H1
          </Button>
          <Button modifiers="icon" onMouseDown={() => this.toggleInlineStyle('BOLD')}>
            <span className="dashicons dashicons-editor-bold"></span>
          </Button>
          <Button modifiers="icon" onMouseDown={() => this.toggleInlineStyle('ITALIC')}>
            <span className="dashicons dashicons-editor-italic"></span>
          </Button>
          <Button modifiers="icon" onMouseDown={() => this.toggleInlineStyle('UNDERLINE')}>
            <span className="dashicons dashicons-editor-underline"></span>
          </Button>
          <Button modifiers="icon" onMouseDown={() => this.toggleInlineStyle('STRIKETHROUGH')}>
            <span className="dashicons dashicons-editor-strikethrough"></span>
          </Button>
          <Button modifiers="icon" onMouseDown={() => this.toggleBlockType('blockquote')}>
            <span className="dashicons dashicons-editor-quote"></span>
          </Button>
          <Button modifiers="icon" onMouseDown={this.openLinkModal.bind(this)}>
            <span className="dashicons dashicons-admin-links"></span>
          </Button>
          <Button modifiers="icon" onMouseDown={this.removeAsLink.bind(this)}>
            <span className="dashicons dashicons-editor-unlink"></span>
          </Button>
          {
            showURLInput &&
            <div className="liveblog-editor-modal">
              <div>
                <div className="liveblog-form">
                  <input
                    className="liveblog-input"
                    onChange={this.onURLChange}
                    value={this.state.url}
                  />
                  <Button type="primary" modifiers="icon" onMouseDown={this.turnIntoLink.bind(this)}>
                    <span className="dashicons dashicons-yes"></span>
                  </Button>
                  <Button type="secondary" modifiers="icon delete" onMouseDown={this.closeLinkModal.bind(this)}>
                    <span className="dashicons dashicons-no"></span>
                  </Button>
                </div>
              </div>
            </div>
          }
        </div>
      </div>
    );
  }
}

Toolbar.propTypes = {
  onChange: PropTypes.func,
  editorState: PropTypes.object,
  domEditor: PropTypes.any,
  plugins: PropTypes.array,
};

export default Toolbar;
