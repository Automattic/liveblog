import React, { Component } from 'react';

// Redux
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

import {Editor, EditorState} from 'draft-js';
import 'draft-js/dist/Draft.css';

import { stateToHTML } from 'draft-js-export-html';
import { stateFromHTML } from 'draft-js-import-html';

// Actions
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

class EditorContainer extends Component {
  constructor(props) {
    super(props);
    this.state = {editorState: EditorState.createEmpty(), update: false };
    this.onChange = (editorState) => this.setState({editorState});
    this.publish = () => {
      if ( this.state.update ) {
        this.props.updateEntry({
          id: this.props.entry.id,
          content: stateToHTML(this.state.editorState.getCurrentContent()) 
        });
        this.props.entryEditClose( this.props.entry.id );
      } else {
        this.props.createEntry({
          content: stateToHTML(this.state.editorState.getCurrentContent()) 
        });
        this.setState({editorState: EditorState.createEmpty()});
      }
      
    }
  }

  componentDidMount() {
   if ( this.props.entry ) {
    this.setState({
      editorState: EditorState.createWithContent( stateFromHTML(this.props.entry.content) ),
      update: true,
    });
   }
  }

  render() {
    if ( this.props.config.can_edit === "false" ) {
      return false;
    }

    return (
      <div className="editor-container">
        <Editor editorState={this.state.editorState} onChange={this.onChange} />
        <button className="editor-publish-button" onClick={this.publish}>Publish Update</button>
      </div>
    )
  }
}

// Map state to props on connected component
// Ideally pick out pieces of state rather than full object
const mapStateToProps = (state) => state

// Map dispatch/actions to props on connected component
const mapDispatchToProps = (dispatch) => bindActionCreators({
  ...apiActions,
  ...userActions,
}, dispatch)

export default connect(mapStateToProps, mapDispatchToProps)(EditorContainer);
