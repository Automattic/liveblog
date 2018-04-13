import React from 'react';

import AceEditor from 'react-ace';
import PropTypes from 'prop-types';
import 'brace/mode/html';
import 'brace/theme/tomorrow';

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
