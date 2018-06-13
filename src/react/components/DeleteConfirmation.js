import React from 'react';
import PropTypes from 'prop-types';

const DeleteConfirmation = ({ onConfirmDelete, onCancel }) => (
  <div className="liveblog-entry-delete-confirm">
    <p>Are you sure you want to delete this entry?</p>
    <div className="liveblog-entry-delete-confirm-buttons">
      <button
        className="liveblog-btn liveblog-btn-small"
        onClick={onCancel}
      >
      Cancel
      </button>
      <button
        className="liveblog-btn liveblog-btn-small liveblog-btn-delete"
        onClick={onConfirmDelete}
      >
      Confirm
      </button>
    </div>
  </div>
);


DeleteConfirmation.propTypes = {
  entry: PropTypes.object,
  onConfirmDelete: PropTypes.func,
  onCancel: PropTypes.func,
};

export default DeleteConfirmation;
