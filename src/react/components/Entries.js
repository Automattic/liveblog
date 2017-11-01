import React from 'react';
import PropTypes from 'prop-types';

import EntryContainer from '../containers/EntryContainer';

const Entries = ({ loading, entries }) => (
  <div className={loading ? 'liveblog-feed liveblog-feed--loading' : 'liveblog-feed'}>
    {
      entries.length === 0 && !loading
        ? <div>There are no entries</div>
        : entries.map((entry, i) => <EntryContainer entry={entry} key={i} />)
    }
    {
      loading && <span className="liveblog-loader dashicons dashicons-update"></span>
    }
  </div>
);

Entries.propTypes = {
  entries: PropTypes.array,
  loading: PropTypes.bool,
};

export default Entries;
