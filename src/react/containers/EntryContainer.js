/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';
import { triggerOembedLoad, timeAgo, formattedTime } from '../utils/utils';
import Editor from '../components/Editor';
import DeleteConfirmation from '../components/DeleteConfirmation';

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
      this.node.scrollIntoView({ block: 'start', behavior: 'instant' });
      this.props.resetScrollOnEntry(`id_${this.props.entry.id}`);
    };
    this.state = {
      showPopup: false,
    };
  }

  togglePopup() {
    this.setState({
      showPopup: !this.state.showPopup,
    });
  }

  componentDidMount() {
    const { activateScrolling } = this.props.entry;
    triggerOembedLoad(this.node);
    if (activateScrolling) this.scrollIntoView();
  }

  componentDidUpdate(prevProps) {
    const { activateScrolling } = this.props.entry;
    if (activateScrolling && activateScrolling !== prevProps.entry.activateScrolling) {
      this.scrollIntoView();
    }
    if (this.props.entry.render !== prevProps.entry.render) {
      triggerOembedLoad(this.node);
    }
  }

  entryActions() {
    const { config } = this.props;
    if (config.is_liveblog_editable !== '1') return false;

    return (
      <footer className="liveblog-entry-tools">
        {
          this.isEditing()
            ? <button className="liveblog-btn liveblog-btn-small" onClick={this.close}>
              Close Editor
            </button>
            : <button className="liveblog-btn liveblog-btn-small" onClick={this.edit}>
              Edit
            </button>
        }
        <button
          className="liveblog-btn liveblog-btn-small liveblog-btn-delete"
          onClick={this.togglePopup.bind(this)}>
          Delete
        </button>
      </footer>
    );
  }

  render() {
    const { entry, config } = this.props;

    return (
      <article
        id={`id_${entry.id}`}
        ref={node => this.node = node}
        className={`liveblog-entry ${entry.key_event ? 'is-key-event' : ''} ${entry.css_classes}`}
      >
        <aside className="liveblog-entry-aside">
          <a className="liveblog-meta-time" href={entry.share_link} target="_blank">
            <span>{timeAgo(entry.entry_time)}</span>
            <span>{formattedTime(entry.entry_time, config.utc_offset, config.date_format)}</span>
          </a>
        </aside>
        <div className="liveblog-entry-main">
          {this.state.showPopup ?
            <DeleteConfirmation
              text="Are you sure you want to delete this entry?"
              onConfirmDelete={this.delete}
              onCancel={this.togglePopup.bind(this)}
            />
            : null
          }
          {
            (entry.authors && entry.authors.length > 0) &&
            <header className="liveblog-meta-authors">
              {
                entry.authors.map(author => (
                  <div className="liveblog-meta-author" key={author.id}>
                    { author.avatar &&
                      <div
                        className="liveblog-meta-author-avatar"
                        dangerouslySetInnerHTML={{ __html: author.avatar }} />
                    }
                    <span className="liveblog-meta-author-name"
                      dangerouslySetInnerHTML={{ __html: author.name }} />
                  </div>
                ))
              }
            </header>
          }
          {
            this.isEditing()
              ? (
                <div className="liveblog-entry-edit">
                  <Editor entry={entry} isEditing={true} />
                </div>
              )
              : (
                <div
                  className="liveblog-entry-content"
                  dangerouslySetInnerHTML={{ __html: entry.render }}
                />
              )
          }
          {this.entryActions()}
        </div>
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
  showPopup: PropTypes.bool,
};

const mapStateToProps = state => state;

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EntryContainer);
