import React from 'react';
import PropTypes from 'prop-types';

import StatusFilter from './StatusFilter';


const UpdateCount = ({ entries, config, total }) => {
  if (!entries.length > 0) return null;

  return (
    <div className="liveblog-update-count">
      Showing {entries.length} of {total} {total > 1 ? config.updates : config.update}
      <StatusFilter />
    </div>
  );
};

UpdateCount.propTypes = {
  entries: PropTypes.array,
  config: PropTypes.object,
  total: PropTypes.number,
};

export default UpdateCount;
