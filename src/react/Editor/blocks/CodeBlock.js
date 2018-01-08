import React, { Component } from 'react';
import PropTypes from 'prop-types';

import Modal from '../../components/Modal';

class CodeBlock extends Component {
  constructor(props) {
    super(props);

    const { contentState, block } = props;
    const { code, title, edit } = contentState.getEntity(block.getEntityAt(0)).getData();

    this.placeholder = 'Enter HTML Code...';

    this.state = {
      edit: edit || false,
      title,
      code,
    };
  }

  componentDidMount() {
    const { setReadOnly } = this.getMetadata();
    const { edit } = this.state;
    if (edit) setReadOnly(true);
  }

  getMetadata() {
    const { contentState, block } = this.props;
    return contentState.getEntity(block.getEntityAt(0)).getData();
  }

  replaceMetadata(data) {
    const { contentState, block } = this.props;
    contentState.mergeEntityData(block.getEntityAt(0), data);
  }

  edit() {
    const { setReadOnly } = this.getMetadata();

    setReadOnly(true);

    this.setState({
      edit: true,
    });
  }

  save() {
    const { code, title } = this.state;
    const { setReadOnly } = this.getMetadata();

    this.setState({
      edit: false,
    });

    this.replaceMetadata({ code, title, edit: false });

    setReadOnly(false);
  }

  cancel() {
    const { code, title, setReadOnly } = this.getMetadata();

    this.setState({
      edit: false,
      code,
      title,
    });

    this.replaceMetadata({ edit: false });

    setReadOnly(false);
  }

  handleChange(event) {
    this.setState({ code: event.target.value });
  }

  render() {
    const { edit, code, title } = this.state;

    return (
      <div className="liveblog-editor-codeblock">
        <span className="liveblog-codeblock-title-container">
          Code Block: <span className="liveblog-codeblock-title">{title}</span>
        </span>
        <span style={{ display: 'inline-block' }} onMouseDown={e => e.preventDefault()}>
          <button
            className="liveblog-btn liveblog-btn-small"
            onClick={this.edit.bind(this)}>
            Edit
          </button>
        </span>

        <Modal active={edit} customInnerClass="liveblog-codeblock-editor">
          <h1 className="liveblog-editor-title">Code Block Editor</h1>
          <div className="liveblog-codeblock-input-container">
            <span>Title:</span>
            <input onChange={event => this.setState({ title: event.target.value })} value={title} />
          </div>
          <textarea
            placeholder={this.placeholder}
            className="liveblog-codeblock-textarea"
            value={code}
            onChange={this.handleChange.bind(this)}
            spellCheck={false}
          />
          <div className="liveblog-codeblock-controls">
            <button
              className="liveblog-btn liveblog-cancel-btn"
              onMouseDown={this.cancel.bind(this)}>
                Cancel
            </button>
            <button
              className="liveblog-btn liveblog-save-btn"
              onMouseDown={this.save.bind(this)}>
                Save
            </button>
          </div>
        </Modal>
      </div>
    );
  }
}

CodeBlock.propTypes = {
  contentState: PropTypes.object,
  block: PropTypes.object,
  setReadOnly: PropTypes.func,
};

export default CodeBlock;
