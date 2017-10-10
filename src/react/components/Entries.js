import React from 'react';
import PropTypes from 'prop-types';

import EntryContainer from '../containers/EntryContainer';

const Entries = ({ entries }) => (
  <div>
    {Object.keys(entries).map((key, i) => <EntryContainer entry={entries[key]} key={i}/>)}
  </div>
);

Entries.propTypes = {
  entries: PropTypes.object,
};

export default Entries;
