import React from 'react';
import PropTypes from 'prop-types';
import { timeAgo, formattedTime } from '../utils/utils';

const EntryMeta = ({ entry, config }) => (
  <header className="liveblog-meta">
    <a className="liveblog-meta-time" href={entry.share_link} target="_blank">
      <span>{timeAgo(entry.entry_time, config.utc_offset, config.date_format)}</span>
      <span>{formattedTime(entry.entry_time, config.utc_offset, config.date_format)}</span>
    </a>
    {
      (entry.authors && entry.authors.length > 0) &&
      <div className="liveblog-meta-authors">
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
