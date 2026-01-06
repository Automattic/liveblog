import React, { useEffect } from 'react';

import AceEditor from 'react-ace';
import { config } from 'ace-builds';
import PropTypes from 'prop-types';
import 'ace-builds/src-noconflict/mode-html';
import 'ace-builds/src-noconflict/theme-tomorrow';

// Configure Ace to not use workers (prevents 404 errors for worker files)
config.set('useWorker', false);

const HTMLInput = ({ container = true, ...props }) => (
  <div className={`liveblog-html-editor ${container ? 'liveblog-html-editor-container' : ''}`}>
    <AceEditor
      mode="html"
      theme="tomorrow"
      name="raw-editor"
      fontSize={13}
      showGutter={false}
      highlightActiveLine={true}
      tabSize={2}
      blockScrolling="Infinity"
      {...props}
    />
  </div>
);

HTMLInput.propTypes = {
  container: PropTypes.bool,
};


export default HTMLInput;
