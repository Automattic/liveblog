import React from 'react';
import PropTypes from 'prop-types';

const addModifiers = modifiers =>
  modifiers
    .split(' ')
    .map(x => `liveblog-btn--${x}`).join(' ');

const Button = ({ children, click, type, modifiers }) => (
  <button
    className={`liveblog-btn ${type && `liveblog-btn--${type}`} ${addModifiers(modifiers)}`}
    onClick={click}>
    {children}
  </button>
);

Button.propTypes = {
  children: PropTypes.string,
  click: PropTypes.func,
  type: PropTypes.string,
  modifiers: PropTypes.string,
};

export default Button;
