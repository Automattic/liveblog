import React from 'react';
import PropTypes from 'prop-types';

const DeleteConfirmation = ({ onConfirmDelete, onCancel }) => {

  return (
    <div className="liveblog-update-btn-container">
      <button
        className="liveblog-btn liveblog-btn-small liveblog-btn-delete"
        onClick={onConfirmDelete}
      >
      Delete
      </button>
      <button
        className="liveblog-btn liveblog-update-btn"
        onClick={onCancel}
      >
      Cancel
      </button>
    </div>
  );
};

DeleteConfirmation.propTypes = {
  entry: PropTypes.object,
  onConfirmDelete: PropTypes.func,
  onCancel: PropTypes.func,
};

export default DeleteConfirmation;
