/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';
import { timeAgo } from '../utils/utils';
import Button from '../components/Button';
import EditorContainer from '../containers/EditorContainer';

class EntryContainer extends Component {
  constructor(props) {
    super(props);

    this.isEditing = () => {
      const { user, entry } = this.props;
      return user.entries[entry.id] && user.entries[entry.id].isEditing;
    };
    this.edit = () => this.props.entryEditOpen(this.props.entry.id);
    this.close = () => this.props.entryEditClose(this.props.entry.id);
    this.delete = () => this.props.deleteEntry(this.props.entry.id);
    this.scrollIntoView = () => {
      this.node.scrollIntoView({ behavior: 'smooth' });
      this.props.resetScrollOnEntry(`id_${this.props.entry.id}`);
    };
  }

  componentDidUpdate(prevProps) {
    const { activateScrolling } = this.props.entry;
    if (activateScrolling && activateScrolling !== prevProps.entry.activateScrolling) {
      this.scrollIntoView();
    }
  }

  entryActions() {
    const { config } = this.props;
    if (config.is_liveblog_editable !== '1') return false;

    return (
      <div>
        {
          this.isEditing()
            ? <Button type="secondary" modifiers="small" click={this.close}>Close Editor</Button>
            : <Button type="secondary" modifiers="small" click={this.edit}>Edit</Button>
        }
        <Button modifiers="small delete" click={this.delete}>Delete</Button>
      </div>
    );
  }

  render() {
    const { entry } = this.props;

    return (
      <article id={`id_${entry.id}`} ref={node => this.node = node} className="liveblog-entry" >
        <header className="liveblog-meta">
          <div className="liveblog-author">
            <div
              className="liveblog-authour-avatar"
              dangerouslySetInnerHTML={{ __html: entry.avatar_img }} />
            <span className="liveblog-author-name" >{entry.author_link}</span>
          </div>
          <span className="liveblog-meta-time">{timeAgo(entry.entry_time)}</span>
        </header>
        {
          this.isEditing()
            ? (
              <div className="wpcom-liveblog-entry-edit">
                <EditorContainer entry={entry} isEditing={true} />
              </div>
            )
            : (
              <div
                className="liveblog-entry-content"
                dangerouslySetInnerHTML={{ __html: entry.content }}
              />
            )
        }
        {this.entryActions()}
      </article>
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
  activateScrolling: PropTypes.bool,
  resetScrollOnEntry: PropTypes.func,
};

const mapStateToProps = state => state;

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EntryContainer);
