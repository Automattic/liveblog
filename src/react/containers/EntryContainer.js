/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as apiActions from '../actions/apiActions';
import * as userActions from '../actions/userActions';
import { triggerOembedLoad, formattedTime } from '../utils/utils';
import EditorContainer from '../containers/EditorContainer';

const authorLink = (author) => {
  let result = '';
  let slug = '';
  if (typeof author !== 'undefined' && author && author.name) {
    slug = author.name.toLowerCase();
    slug = slug.replace( /[^%a-z0-9 _-]/g, '' );
    slug = slug.replace( /\s+/g, '-' );
    slug = slug.replace( /-+/g, '-' );

    result = `/contributors/${slug}/`;
  }
  return result;
};

class EntryContainer extends Component {
  constructor(props) {
    super(props);

    this.isEditing = () => {
      const { user, entry } = this.props;
      return user.entries[entry.id] && user.entries[entry.id].isEditing;
    };
    this.edit = (event) => {
      event.preventDefault();
      this.props.entryEditOpen(this.props.entry.id);
    };
    this.close = (event) => {
      event.preventDefault();
      this.props.entryEditClose(this.props.entry.id);
    };
    this.delete = (event) => {
      event.preventDefault();
      this.props.deleteEntry(this.props.entry.id);
    };
    this.scrollIntoView = () => {
      this.node.scrollIntoView({ block: 'start', behavior: 'instant' });
      this.props.resetScrollOnEntry(`id_${this.props.entry.id}`);
    };
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
  }

  entryActions() {
    const { config } = this.props;
    if (!config.is_admin && (config.is_liveblog_editable !== '1' || config.backend_liveblogging === '1')) {
      return false;
    }

    return (
      <footer className="liveblog-entry-tools">
        {
          this.isEditing()
            ? <button
              className="button button-large liveblog-btn liveblog-btn-small"
              onClick={this.close}
            >
            Close Editor
            </button>
            : <button
              className="button button-large liveblog-btn liveblog-btn-smallx"
              onClick={this.edit}
            >
              Edit
            </button>
        }
        <button
          className="button button-large button-link-delete liveblog-btn liveblog-btn-small liveblog-btn-delete"
          onClick={this.delete}
        >
          Delete
        </button>
      </footer>
    );
  }

  entryShare() {
    const { entry } = this.props;

    return (
      <div className="liveblog-share" id={`liveblog-update-${entry.id}-share`} data-update-id={entry.id}>
        <button className="share-social share-facebook"></button>
        <button className="share-social share-twitter"></button>
      </div>
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
        <div className="liveblog-entry-main">
          {
            (entry.authors && entry.authors.length > 0) &&
            <div className="liveblog-meta-avatars">
              {
                entry.authors.map(author => (
                  author.id &&
                  <a
                    key={author.id}
                    className="liveblog-meta-avatar"
                    href={authorLink(author)}
                    dangerouslySetInnerHTML={{ __html: author.avatar }} />
                ))
              }
            </div>
          }
          <header className="liveblog-meta">
            {
              (entry.authors && entry.authors.length > 0) &&
              <div className="liveblog-meta-authors">
                {
                  entry.authors.map((author, index, list) => (
                    author.id &&
                    <span className="liveblog-meta-author" key={author.id}>
                      <a href={authorLink(author)}>{author.name}</a>
                      {(index < list.length - 1) && ', '}
                    </span>
                  ))
                }
              </div>
            }
            <a className="liveblog-meta-time" href={entry.share_link}>
              <abbr title={formattedTime(entry.entry_time, config.utc_offset, 'c')} className="liveblog-timestamp">{formattedTime(entry.entry_time, config.utc_offset, config.time_format)}</abbr>
            </a>
          </header>
          {
            this.isEditing()
              ? (
                <div className="liveblog-entry-edit">
                  <EditorContainer
                    entry={entry}
                    isEditing={true}
                    usetinymce={config.usetinymce}
                  />
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
          {this.entryShare()}
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
};

const mapStateToProps = state => state;

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...userActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(EntryContainer);
