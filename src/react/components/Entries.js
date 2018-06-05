import React from 'react';
import PropTypes from 'prop-types';

import EntryContainer from '../containers/EntryContainer';

const Entries = ({ loading, entries }) => (
  <div className={loading ? 'liveblog-feed is-loading' : 'liveblog-feed'}>
    {
      entries.length === 0 && !loading
        ? <div className="liveblog-empty-message">There are no entries on this page.</div>
        : entries.map(entry => <EntryContainer entry={entry} key={entry.id} />)
    }
  </div>
);

Entries.propTypes = {
  entries: PropTypes.array,
  loading: PropTypes.bool,
};

export default Entries;
