/* eslint-disable react/display-name */
/* eslint-disable react/prop-types */

import React from 'react';

const DragAndFocus = Component => ({ block, blockProps, ...props }) => {
  /**
   * Set dataTransfer data on drag. We set the text key because nothing else will
   * move the cursor which is the behavior we want.
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
      onMouseDown={blockProps.setSelectionToBlock && !blockProps.isFocused
        ? blockProps.setSelectionToBlock
        : null
      }
    >
      <Component block={block} blockProps={blockProps} {...props} />
    </div>
  );
};

export default DragAndFocus;
