import React from 'react';
import PropTypes from 'prop-types';

const Event = ({ event, click, onDelete }) => (
  <li className="liveblog-event">
    <span
      className="liveblog-event-content"
      onClick={click}
      dangerouslySetInnerHTML={{ __html: event.key_event_content }}
    />
    <span className="liveblog-event-delete" onClick={onDelete}>x</span>
  </li>
);

Event.propTypes = {
  event: PropTypes.object,
  click: PropTypes.func,
  onDelete: PropTypes.func,
};

export default Event;
