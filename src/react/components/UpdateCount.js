import React from 'react';
import PropTypes from 'prop-types';

const UpdateCount = ({ entries, config }) => {
  if (!entries.length > 0) return null;

  return (
    <div className="liveblog-update-count">
      {entries.length} {config.updates}
    </div>
  );
};

UpdateCount.propTypes = {
  entries: PropTypes.array,
  config: PropTypes.object,
};

export default UpdateCount;
