import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { RichUtils, EditorState } from 'draft-js';
import Button from '../components/Button';

class EditorToolbarContainer extends Component {
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
    const contentState = editorState.getCurrentContent();
    const contentStateWithEntity = contentState.createEntity(
      'LINK',
      'SEGMENTED',
      { url },
    );

    const selection = editorState.getSelection();
    const newEditorState = EditorState.set(editorState, {
      currentContent: contentStateWithEntity,
    });

    onChange(
      RichUtils.toggleLink(
        newEditorState,
        selection,
        contentStateWithEntity.getLastCreatedEntityKey(),
      ),
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
    const { showURLInput } = this.state;

    return (
      <div className="liveblog-toolbar">
        <Button type="secondary" modifiers="icon" onMouseDown={() => this.toggleBlockType('header-one')}>
          H1
        </Button>
        <Button type="secondary" modifiers="icon" onMouseDown={() => this.toggleInlineStyle('BOLD')}>
          <span className="dashicons dashicons-editor-bold"></span>
        </Button>
        <Button type="secondary" modifiers="icon" onMouseDown={() => this.toggleInlineStyle('ITALIC')}>
          <span className="dashicons dashicons-editor-italic"></span>
        </Button>
        <Button type="secondary" modifiers="icon" onMouseDown={() => this.toggleInlineStyle('UNDERLINE')}>
          <span className="dashicons dashicons-editor-underline"></span>
        </Button>
        <Button type="secondary" modifiers="icon" onMouseDown={() => this.toggleInlineStyle('STRIKETHROUGH')}>
          <span className="dashicons dashicons-editor-strikethrough"></span>
        </Button>
        <Button type="secondary" modifiers="icon" onMouseDown={() => this.toggleBlockType('blockquote')}>
          <span className="dashicons dashicons-editor-quote"></span>
        </Button>
        <Button type="secondary" modifiers="icon" onMouseDown={this.openLinkModal.bind(this)}>
          <span className="dashicons dashicons-admin-links"></span>
        </Button>
        <Button type="secondary" modifiers="icon" onMouseDown={this.removeAsLink.bind(this)}>
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
    );
  }
}

EditorToolbarContainer.propTypes = {
  onChange: PropTypes.func,
  editorState: PropTypes.object,
  domEditor: PropTypes.any,
  plugins: PropTypes.array,
};

export default EditorToolbarContainer;

export const Link = ({ entityKey, children, contentState }) => {
  const { url } = contentState.getEntity(entityKey).getData();
  return (
    <a href={url}>{children}</a>
  );
};

// @todo correct proptypes
Link.propTypes = {
  entityKey: PropTypes.any,
  children: PropTypes.any,
  contentState: PropTypes.any,
};

export const findLinkEntities = (contentBlock, callback, contentState) => {
  contentBlock.findEntityRanges(
    (character) => {
      const entityKey = character.getEntity();
      return (
        entityKey !== null &&
        contentState.getEntity(entityKey).getType() === 'LINK'
      );
    },
    callback,
  );
};
