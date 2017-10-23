import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import { Editor, EditorState } from 'draft-js';
import 'draft-js/dist/Draft.css';
import { stateToHTML } from 'draft-js-export-html';
import { stateFromHTML } from 'draft-js-import-html';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';
import Button from '../components/Button';

class EditorContainer extends Component {
  constructor(props) {
    super(props);
    this.state = {
      editorState: EditorState.createEmpty(),
      update: false,
    };

    this.onChange = editorState => this.setState({ editorState });

    this.publish = () => {
      const { updateEntry, entry, entryEditClose, createEntry } = this.props;
      const { update, editorState } = this.state;

      if (update) {
        updateEntry({
          id: entry.id,
          content: stateToHTML(editorState.getCurrentContent()),
        });
        entryEditClose(entry.id);
      } else {
        createEntry({
          content: stateToHTML(editorState.getCurrentContent()),
        });
        this.setState({ editorState: EditorState.createEmpty() });
      }
    };
  }

  componentDidMount() {
    const { entry } = this.props;

    if (entry) {
      this.setState({
        editorState: EditorState.createWithContent(stateFromHTML(entry.content)),
        update: true,
      });
    }
  }

  render() {
    const { config, isEditing } = this.props;

    if (config.is_liveblog_editable !== '1') return false;

    return (
      <div className="editor-container">
        <Editor
          editorState={this.state.editorState}
          onChange={this.onChange}
        />
        <Button type="primary" modifiers="wide" click={this.publish}>
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
};

const mapStateToProps = state => state;

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions },
  dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EditorContainer);
