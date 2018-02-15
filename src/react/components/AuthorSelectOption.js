import React, { Component } from 'react';
import PropTypes from 'prop-types';

class AuthorSelectOption extends Component {
  handleMouseDown(event) {
    const { onSelect, option } = this.props;
    event.preventDefault();
    event.stopPropagation();
    onSelect(option, event);
  }

  handleMouseEnter(event) {
    this.props.onFocus(this.props.option, event);
  }

  handleMouseMove(event) {
    const { isFocused, onFocus, option } = this.props;
    if (isFocused) return;
    onFocus(option, event);
  }

  render() {
    const { className, option } = this.props;
    return (
      <div
        className={`${className} liveblog-popover-item`}
        onMouseDown={this.handleMouseDown.bind(this)}
        onMouseEnter={this.handleMouseEnter.bind(this)}
        onMouseMove={this.handleMouseMove.bind(this)}
      >
        { option.avatar && <div dangerouslySetInnerHTML={{ __html: option.avatar }} /> }
        {option.name}
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
