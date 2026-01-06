import React from 'react';
import PropTypes from 'prop-types';

/**
 * Custom Option component for react-select author picker.
 * Must spread innerProps and use innerRef for keyboard navigation to work.
 */
const AuthorSelectOption = ({ innerRef, innerProps, data, isFocused, isSelected, isDisabled }) => {
  // Render hint option differently
  if (data.isHint) {
    return (
      <div
        ref={innerRef}
        className="liveblog-popover-item liveblog-popover-hint"
      >
        {data.name}
      </div>
    );
  }

  return (
    <div
      ref={innerRef}
      {...innerProps}
      className={`liveblog-popover-item ${isFocused ? 'is-focused' : ''} ${isSelected ? 'is-selected' : ''} ${isDisabled ? 'is-disabled' : ''}`}
    >
      {data.avatar && <div dangerouslySetInnerHTML={{ __html: data.avatar }} />}
      {data.name}
    </div>
  );
};

AuthorSelectOption.propTypes = {
  innerRef: PropTypes.oneOfType([PropTypes.func, PropTypes.object]),
  innerProps: PropTypes.object,
  data: PropTypes.object,
  isFocused: PropTypes.bool,
  isSelected: PropTypes.bool,
  isDisabled: PropTypes.bool,
};

export default AuthorSelectOption;
