import React from 'react';
import PropTypes from 'prop-types';

const Event = ({ event, click, onDelete }) => (
  <li >
    <div onClick={click} dangerouslySetInnerHTML={{ __html: event.content }} />
    <span onClick={onDelete}>x</span>
  </li>
);

Event.propTypes = {
  event: PropTypes.object,
  click: PropTypes.func,
  onDelete: PropTypes.func,
};

export default Event;
