import React from 'react';
import PropTypes from 'prop-types';
import { timeAgo, formattedTime } from '../utils/utils';

const EntryMeta = ({ entry, config }) => (
  <header className="liveblog-meta">
    <a className="liveblog-meta-time" href={entry.share_link} target="_blank">
      <span>{timeAgo(entry.entry_time, config.utc_offset, config.date_format)}</span>
      <span>{formattedTime(entry.entry_time, config.utc_offset, config.date_format)}</span>
    </a>
    <div className="liveblog-meta-author">
      { entry.author.avatar &&
        <div
          className="liveblog-meta-author-avatar"
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
                  className="liveblog-meta-author-avatar"
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
  config: PropTypes.object,
};

export default EntryMeta;
