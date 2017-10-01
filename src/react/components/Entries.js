import React from 'react';

import EntryContainer from '../containers/EntryContainer';

const Entries = ({entries}) => (
  <div>
    {entries.map( (entry, i) => <EntryContainer entry={entry} key={i}/>)}
  </div>
)

export default Entries;
