import React from 'react';
import PropTypes from 'prop-types';

const ModifiedDeleteConfirmation = ({ text, onConfirmDelete, onCancel }) => (
  <div className="liveblog-entry-delete-confirm">
    <p>{text}</p>
    <div className="liveblog-entry-delete-confirm-buttons">
      <button
        className="liveblog-btn liveblog-btn-small"
        onClick={onCancel}
      >
      No, I have changed my mind
      </button>
      <button
        className="liveblog-btn liveblog-btn-small liveblog-btn-delete"
        onClick={onConfirmDelete}
      >
      I still want to delete this entry
      </button>
    </div>
  </div>
);

ModifiedDeleteConfirmation.propTypes = {
  entry: PropTypes.object,
  onConfirmDelete: PropTypes.func,
  onCancel: PropTypes.func,
  text: PropTypes.string,
};

export default ModifiedDeleteConfirmation;
