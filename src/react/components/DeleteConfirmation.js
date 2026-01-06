import React from 'react';
import PropTypes from 'prop-types';
import { __ } from '@wordpress/i18n';

const DeleteConfirmation = ({ text, onConfirmDelete, onCancel }) => (
  <div className="liveblog-entry-delete-confirm">
    <p>{text}</p>
    <div className="liveblog-entry-delete-confirm-buttons">
      <button
        className="liveblog-btn liveblog-btn-small"
        onClick={onCancel}
      >
        { __( 'Cancel', 'liveblog' ) }
      </button>
      <button
        className="liveblog-btn liveblog-btn-small liveblog-btn-delete"
        onClick={onConfirmDelete}
      >
        { __( 'Confirm', 'liveblog' ) }
      </button>
    </div>
  </div>
);

DeleteConfirmation.propTypes = {
  entry: PropTypes.object,
  onConfirmDelete: PropTypes.func,
  onCancel: PropTypes.func,
  text: PropTypes.string,
};

export default DeleteConfirmation;
