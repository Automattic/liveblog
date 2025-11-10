import React, { Component } from 'react';
import PropTypes from 'prop-types';

class AuthorSelectOption extends Component {
  handleMouseDown(event) {
    const { onSelect, option, data, selectOption } = this.props;
    const item = data || option;
    event.preventDefault();
    event.stopPropagation();
    // react-select v5 uses selectOption instead of onSelect
    if (selectOption) {
      selectOption(item);
    } else if (onSelect) {
      onSelect(item, event);
    }
  }

  handleMouseEnter(event) {
    const { onFocus, option, data } = this.props;
    const item = data || option;
    if (onFocus) onFocus(item, event);
  }

  handleMouseMove(event) {
    const { isFocused, onFocus, option, data } = this.props;
    if (isFocused) return;
    const item = data || option;
    if (onFocus) onFocus(item, event);
  }

  render() {
    const { className, option, data } = this.props;
    // react-select v5 uses 'data' prop instead of 'option'
    const item = data || option || {};
    return (
      <div
        className={`${className} liveblog-popover-item`}
        onMouseDown={this.handleMouseDown.bind(this)}
        onMouseEnter={this.handleMouseEnter.bind(this)}
        onMouseMove={this.handleMouseMove.bind(this)}
      >
        { item.avatar && <div dangerouslySetInnerHTML={{ __html: item.avatar }} /> }
        {item.name}
      </div>
    );
  }
}

AuthorSelectOption.propTypes = {
  onSelect: PropTypes.func,
  option: PropTypes.object,
  onFocus: PropTypes.func,
  isFocused: PropTypes.bool,
  className: PropTypes.string,
};

export default AuthorSelectOption;
