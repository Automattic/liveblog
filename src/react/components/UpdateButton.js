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
        View {polling.length} New {polling.length > 1 ? 'Posts' : 'Post'}
      </button>
    </div>
  );
};

UpdateButton.propTypes = {
  polling: PropTypes.array,
  click: PropTypes.func,
};

export default UpdateButton;
