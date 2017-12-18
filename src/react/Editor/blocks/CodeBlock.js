import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Modal from 'react-modal';

class CodeBlock extends Component {
  constructor(props) {
    super(props);

    const { contentState, block } = props;
    const { code } = contentState.getEntity(block.getEntityAt(0)).getData();

    this.state = {
      editing: false,
      code,
    };
  }

  getMetadata() {
    const { contentState, block } = this.props;
    return contentState.getEntity(block.getEntityAt(0)).getData();
  }

  replaceMetadata(key, data) {
    const { contentState, block } = this.props;
    contentState.mergeEntityData(block.getEntityAt(0), { [key]: data });
  }

  edit() {
    const { toggleReadOnly } = this.getMetadata();

    this.setState({
      edit: true,
    });

    toggleReadOnly();
  }

  save() {
    const { toggleReadOnly } = this.getMetadata();
    const { code } = this.state;

    this.setState({
      edit: false,
    });

    this.replaceMetadata('code', code);
    toggleReadOnly();
  }

  cancel() {
    const { code, toggleReadOnly } = this.getMetadata();

    this.setState({
      edit: false,
      code,
    });

    toggleReadOnly();
  }

  handleChange(event) {
    this.setState({ code: event.target.value });
  }

  render() {
    const { edit, code } = this.state;

    return (
      <div className="liveblog-editor-codeblock">
        <span className="liveblog-codeblock-preview">{code}</span>
        <button className="liveblog-btn liveblog-btn-small" onMouseDown={this.edit.bind(this)}>
          Edit
        </button>
        <Modal isOpen={edit} ariaHideApp={false}>
          woop
        </Modal>
        {/* {
          edit && (
            <div className="liveblog-editor-modal">
              <h2 className="liveblog-editor-subtitle">Code Block Editor</h2>
              <textarea
                value={code}
                onChange={this.handleChange.bind(this)}
              />
              <button
                className="liveblog-btn liveblog-btn"
                onMouseDown={this.cancel.bind(this)}>
                  Cancel
              </button>
              <button
                className="liveblog-btn liveblog-btn"
                onMouseDown={this.save.bind(this)}>
                  Save
              </button>
            </div>
          )
        } */}
      </div>
    );
  }
}

CodeBlock.propTypes = {
  contentState: PropTypes.object,
  block: PropTypes.object,
};

export default CodeBlock;
