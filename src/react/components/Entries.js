import React from 'react';
import PropTypes from 'prop-types';

import EntryContainer from '../containers/EntryContainer';

const Entries = ({ entries }) => (
  <div>
    {entries.map((entry, i) => <EntryContainer entry={entry} key={i}/>)}
  </div>
);

Entries.propTypes = {
  entries: PropTypes.array,
};

export default Entries;
