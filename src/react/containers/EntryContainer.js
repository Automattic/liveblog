import React, { Component } from 'react';
import PropTypes from 'prop-types';

// Redux
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

// Actions
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';

// Component to connect to store
import EditorContainer from '../containers/EditorContainer';

class EntryContainer extends Component {
  constructor(props) {
    super(props);

    this.createMarkup = () => ({ __html: this.props.entry.content });
    this.edit = () => this.props.entryEditOpen(this.props.entry.id);
    this.close = () => this.props.entryEditClose(this.props.entry.id);
    this.delete = () => this.props.deleteEntry(this.props.entry.id);
  }

  entryActions() {
    if (this.props.config.can_edit === 'false') {
      return false;
    }

    return (
      <div className="wpcom-liveblog-entry-actions">
        <button className="wpcom-liveblog-entry-actions-edit" onClick={this.edit}>
          Edit
        </button>
        <button className="wpcom-liveblog-entry-actions-delete" onClick={this.delete}>
          Delete
        </button>
      </div>
    );
  }

  render() {
    if (
      this.props.user.entries[this.props.entry.id] &&
      this.props.user.entries[this.props.entry.id].isEditing
    ) {
      return (
        <div className="wpcom-liveblog-entry-edit">
          <button className="wpcom-liveblog-entry-edit-action" onClick={this.close}>Close</button>
          <EditorContainer entry={this.props.entry} />
        </div>
      );
    }

    return (
      <div className="wpcom-liveblog-entry">
        <div
          className="wpcom-liveblog-entry-content"
          dangerouslySetInnerHTML={this.createMarkup()}
        />
        {this.entryActions()}
      </div>
    );
  }
}

EntryContainer.propTypes = {
  user: PropTypes.object,
  config: PropTypes.object,
  entry: PropTypes.object,
  entryEditOpen: PropTypes.func,
  entryEditClose: PropTypes.func,
  deleteEntry: PropTypes.func,
};

// Map state to props on connected component
const mapStateToProps = state => state;

// Map dispatch/actions to props on connected component
const mapDispatchToProps = dispatch =>
  bindActionCreators({ ...apiActions, ...userActions }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EntryContainer);
