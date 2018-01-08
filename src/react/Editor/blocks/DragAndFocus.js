/* eslint-disable react/display-name */
/* eslint-disable react/prop-types */

import React from 'react';

const DragAndFocus = Component => ({ block, blockProps, ...props }) => {
  /**
   * Set dataTransfer data on drag. We set the text key because nothing else will
   * move the cursor which is the behavior we want. It is worth noting that in development
   * builds of React this doesn't work in IE11. You can read why here -
   * https://github.com/facebook/react/issues/5700
   */
  const startDrag = (event) => {
    event.dataTransfer.dropEffect = 'move'; // eslint-disable-line no-param-reassign
    event.dataTransfer.setData('text', `DRAFT_BLOCK:${block.getKey()}`);
  };

  return (
    <div
      className={`liveblog-draggable-block ${blockProps.isFocused ? 'is-focused' : ''}`}
      draggable={true}
      onDragStart={startDrag}
    >
      <Component block={block} blockProps={blockProps} {...props} />
    </div>
  );
};

export default DragAndFocus;
