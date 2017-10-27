import React from 'react';
import PropTypes from 'prop-types';
import { timeAgo } from '../utils/utils';

const Event = ({ event, click, onDelete, canEdit }) => (
  <li className="liveblog-event">
    <div className="liveblog-event-body">
      <div className="liveblog-meta-time" >{timeAgo(event.entry_time)}</div>
      <div
        className="liveblog-event-content"
        onClick={click}
        dangerouslySetInnerHTML={{ __html: event.key_event_content }}
      />
    </div>
    {canEdit && <span className="liveblog-event-delete" onClick={onDelete}>x</span>}
  </li>
);

Event.propTypes = {
  event: PropTypes.object,
  click: PropTypes.func,
  onDelete: PropTypes.func,
  canEdit: PropTypes.bool,
};

export default Event;
