import React from 'react';
import PropTypes from 'prop-types';

const UpdateButton = ({ polling, click }) => {
  if (!polling.length > 0) return false;

  return (
    <div className="liveblog-update-btn-container">
      <button
        className="liveblog-btn liveblog-update-btn"
        onClick={click}
      >
        {polling.length} new {polling.length > 1 ? 'entries' : 'entry'} available
      </button>
    </div>
  );
};

UpdateButton.propTypes = {
  polling: PropTypes.array,
  click: PropTypes.func,
};

export default UpdateButton;
