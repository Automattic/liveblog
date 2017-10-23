import React from 'react';
import PropTypes from 'prop-types';

const UpdateButton = ({ polling, click }) => {
  if (!polling.length > 0) return false;

  return (
    <button
      className="liveblog-btn liveblog-btn--primary liveblog-btn--wide"
      style={{ position: 'sticky', top: '50px' }}
      onClick={click}>
      {polling.length} new {polling.length > 1 ? 'entries' : 'entry'} available
    </button>
  );
};

UpdateButton.propTypes = {
  polling: PropTypes.array,
  click: PropTypes.func,
};

export default UpdateButton;
