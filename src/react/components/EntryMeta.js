import React from 'react';
import PropTypes from 'prop-types';
import { timeAgo, formattedTime } from '../utils/utils';

const EntryMeta = ({ entry }) => (
  <header className="liveblog-meta">
    <div className="liveblog-meta-time">
      <span>{timeAgo(entry.entry_time)}</span>
      <span>{formattedTime(entry.entry_time)}</span>
    </div>
    <div className="liveblog-meta-author">
      { entry.author.avatar &&
        <div
          className="liveblog-meta-authour-avatar"
          dangerouslySetInnerHTML={{ __html: entry.author.avatar }} />
      }
      <span className="liveblog-meta-author-name"
        dangerouslySetInnerHTML={{ __html: entry.author.name }} />
    </div>
    {
      (entry.contributors && entry.contributors.length > 0) &&
      <div className="liveblog-meta-contributors">
        {
          entry.contributors.filter(x => x.id !== entry.author.id).map(contributor => (
            <div className="liveblog-meta-author" key={contributor.id}>
              { contributor.avatar &&
                <div
                  className="liveblog-meta-authour-avatar"
                  dangerouslySetInnerHTML={{ __html: contributor.avatar }} />
              }
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
