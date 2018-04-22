import React, { Component } from 'react';
import PropTypes from 'prop-types';

import Button from '../Button';
import HTMLInput from '../../components/HTMLInput';

class CodeBlock extends Component {
  constructor(props) {
    super(props);
    const { code, title } = props.getMetadata();
    this.placeholder = 'Insert HTML...';

    this.state = {
      title,
      code,
    };
  }

  /**
   * Handle save.
   */
  save() {
    const { code, title } = this.state;
    const { replaceMetadata, setEditMode } = this.props;
    replaceMetadata({ code, title }, true);
    setEditMode(false);
  }

  /**
   * Set edit mode to false.
   */
  cancel() {
    const { setEditMode, getMetadata } = this.props;
    const { code, title } = getMetadata();
    this.setState({ code, title });
    setEditMode(false);
  }

  /**
   * Handle onChange event to keep textare in sync.
   * @param {object} event
   */
  handleChange(value) {
    this.setState({ code: value });
  }

  render() {
    const { code, title } = this.state;
    const { edit, setEditMode, removeBlock } = this.props;

    return (
      <div className="liveblog-block-inner liveblog-editor-codeblock">
        <div className="liveblog-block-header">
          <span className="liveblog-block-title-container">
            <span className="liveblog-block-title">{ !edit ? 'HTML Block:' : 'Title:' }</span>
            {!edit
              ? <span>{title}</span>
              : <input
                value={title}
                onChange={event => this.setState({ title: event.target.value })}
              />
            }
          </span>
          <div className="liveblog-editor-actions">
            {edit &&
              <span style={{ display: 'inline-block' }} onMouseDown={e => e.preventDefault()}>
                <button
                  className="liveblog-editor-btn liveblog-editor-cancel-btn"
                  onClick={this.cancel.bind(this)}>
                  Cancel
                </button>
              </span>
            }
            <span style={{ display: 'inline-block' }} onMouseDown={e => e.preventDefault()}>
              <button
                className="liveblog-editor-btn liveblog-editor-action-btn"
                onClick={!edit ? () => setEditMode(true) : this.save.bind(this)}>
                {edit ? 'Save' : 'Edit'}
              </button>
            </span>
            { !edit &&
              <Button
                onMouseDown={() => removeBlock()}
                icon="no-alt"
                classes="liveblog-editor-delete"
              />
            }
          </div>
        </div>
        {
          edit &&
          <HTMLInput
            container={false}
            value={code}
            onChange={this.handleChange.bind(this)}
            height="175px"
            width="100%"
          />
        }
      </div>
    );
  }
}

CodeBlock.propTypes = {
  setEditMode: PropTypes.func,
  getMetadata: PropTypes.func,
  replaceMetadata: PropTypes.func,
  edit: PropTypes.bool,
  removeBlock: PropTypes.func,
};

export default CodeBlock;
