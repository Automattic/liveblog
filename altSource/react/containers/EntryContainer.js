/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';
import { triggerOembedLoad, formattedTime } from '../utils/utils';
import EditorContainer from '../containers/EditorContainer';
import ModifiedDeleteConfirmation from '../components/ModifiedDeleteConfirmation';

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
    const authorClass = (entry.authors && entry.authors.length > 1) ? 'liveblog-meta-author liveblog-meta-author--multiple' : 'liveblog-meta-author';

    return (
      <article
        id={`id_${entry.id}`}
        ref={node => this.node = node}
        className={`liveblog-entry ${entry.key_event ? 'is-key-event' : ''} ${entry.css_classes}`}
      >
        <div className="liveblog-time-share">
          <a className="liveblog-meta-time" href={entry.share_link} target="_blank" rel="noopener noreferrer">
            <span className="liveblog-meta-date-format">{formattedTime(entry.entry_time, config.utc_offset, config.date_format)}</span>
            <span className="liveblog-meta-time-format">{formattedTime(entry.entry_time, config.utc_offset, config.time_format)}</span>
          </a>
          <a className="liveblog-meta-share" href={`${entry.tweet_link}`} target="_blank" rel="noopener noreferrer">
            Share this post
            <svg className="svg-inline svg-inline--fa-twitter" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="26" height="28" viewBox="0 0 26 28">
              <path d="M25.312 6.375c-.688 1-1.547 1.891-2.531 2.609.016.219.016.438.016.656 0 6.672-5.078 14.359-14.359 14.359-2.859 0-5.516-.828-7.75-2.266.406.047.797.063 1.219.063 2.359 0 4.531-.797 6.266-2.156-2.219-.047-4.078-1.5-4.719-3.5.313.047.625.078.953.078.453 0 .906-.063 1.328-.172-2.312-.469-4.047-2.5-4.047-4.953v-.063c.672.375 1.453.609 2.281.641-1.359-.906-2.25-2.453-2.25-4.203 0-.938.25-1.797.688-2.547 2.484 3.062 6.219 5.063 10.406 5.281-.078-.375-.125-.766-.125-1.156 0-2.781 2.25-5.047 5.047-5.047 1.453 0 2.766.609 3.687 1.594 1.141-.219 2.234-.641 3.203-1.219-.375 1.172-1.172 2.156-2.219 2.781 1.016-.109 2-.391 2.906-.781z"/>
            </svg>
          </a>
        </div>
        <aside className="liveblog-entry-aside">
          {
            (entry.authors && entry.authors.length > 0) &&
            <header className="liveblog-meta-authors">
              {
                entry.authors.map(author => (
                  <div className={authorClass} key={author.id}>
                    { (author.avatar && entry.authors.length === 1) &&
                    <div
                      className="liveblog-meta-author-avatar"
                      dangerouslySetInnerHTML={{ __html: author.avatar }} />
                    }
                    { (author.name) &&
                    <span className="liveblog-meta-author-name"
                      dangerouslySetInnerHTML={{ __html: author.name }} />
                    }
                    { (author.name && entry.authors.length > 1) &&
                    <span className="liveblog-meta-author-separator"
                      dangerouslySetInnerHTML={{ __html: 'and' }} />
                    }
                  </div>
                ))
              }
            </header>
          }
        </aside>
        <div className="liveblog-entry-main">
          {this.state.showPopup ?
            <ModifiedDeleteConfirmation
              text="Are you sure you want to delete this entry?"
              onConfirmDelete={this.delete}
              onCancel={this.togglePopup.bind(this)}
            />
            : null
          }
          {
            this.isEditing()
              ? (
                <div className="liveblog-entry-edit">
                  <EditorContainer entry={entry} isEditing={true} />
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
