import React from 'react';
import PropTypes from 'prop-types';
import { timeAgo, formattedTime } from '../utils/utils';

const EntryMeta = ({ entry, authorEditEnabled }) => (
  <header className="liveblog-meta">
    <div className="liveblog-meta-time">
      <span>{timeAgo(entry.entry_time)}</span>
      <span>{formattedTime(entry.entry_time)}</span>
    </div>
    <div className="liveblog-meta-author">
      <div
        className="liveblog-meta-authour-avatar"
        dangerouslySetInnerHTML={{ __html: entry.author.avatar }} />
      <span className="liveblog-meta-author-name"
        dangerouslySetInnerHTML={{ __html: entry.author.name }} />
    </div>
    {
      authorEditEnabled &&
      <div className="liveblog-meta-contributors">
        {
          entry.contributors.map(contributor => (
            <div className="liveblog-meta-author" key={contributor.id}>
              <div
                className="liveblog-meta-authour-avatar"
                dangerouslySetInnerHTML={{ __html: contributor.avatar }} />
              <span className="liveblog-meta-author-name"
                dangerouslySetInnerHTML={{ __html: contributor.name }} />
            </div>
          ))
        }
      </div>
    }
  </header>
);

EntryMeta.propTypes = {
  entry: PropTypes.object,
  authorEditEnabled: PropTypes.bool,
};

export default EntryMeta;
