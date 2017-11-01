import React from 'react';
import PropTypes from 'prop-types';
import { timeAgo } from '../utils/utils';

const Event = ({ event, click, onDelete, canEdit }) => (
  <li className="liveblog-event">
    <div className="liveblog-event-body">
      <div className="liveblog-event-meta" >{timeAgo(event.entry_time)}</div>
      <div>
        {canEdit && <span className="dashicons dashicons-no-alt liveblog-event-delete" onClick={onDelete}></span>}
        <span
          className="liveblog-event-content"
          onClick={click}
          dangerouslySetInnerHTML={{ __html: event.key_event_content }}
        />
      </div>
    </div>
  </li>
);

Event.propTypes = {
  event: PropTypes.object,
  click: PropTypes.func,
  onDelete: PropTypes.func,
  canEdit: PropTypes.bool,
};

export default Event;
