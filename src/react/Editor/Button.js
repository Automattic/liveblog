import React from 'react';
import PropTypes from 'prop-types';

const Button = ({ click, onMouseDown, icon, classes, readOnly }) => (
  <span style={{ display: 'inline-block' }} onMouseDown={onMouseDown ? e => e.preventDefault() : null}>
    <button
      disabled={readOnly}
      className={`liveblog-btn liveblog-editor-btn ${classes}`}
      onClick={onMouseDown || click}>
      <span className={`dashicons dashicons-${icon}`}></span>
    </button>
  </span>
);

Button.propTypes = {
  click: PropTypes.func,
  onMouseDown: PropTypes.func,
  icon: PropTypes.string,
  classes: PropTypes.string,
  readOnly: PropTypes.bool,
};

export default Button;
