import ReactDOM from 'react-dom';
import React, { Component } from 'react';
import PropTypes from 'prop-types';

class Modal extends Component {
  constructor(props) {
    super(props);
    this.element = document.createElement('div');
  }

  componentDidUpdate(prevProps) {
    const { active } = this.props;

    if (prevProps.active === active) return;

    if (active) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'auto';
    }
  }

  componentDidMount() {
    document.body.appendChild(this.element);
  }

  componentWillUnmount() {
    this.element.parentNode.removeChild(this.element);
  }

  renderModal() {
    const { active, children } = this.props;

    if (!active) return false;

    return (
      <div className="liveblog-modal">
        <div className="liveblog-modal-inner">
          {children}
        </div>
      </div>
    );
  }

  render() {
    return ReactDOM.createPortal(
      this.renderModal(),
      this.element,
    );
  }
}

Modal.propTypes = {
  active: PropTypes.bool,
  children: PropTypes.any,
  customInnerClass: PropTypes.string,
};

export default Modal;
