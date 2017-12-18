/* eslint-disable consistent-return */
/* eslint-disable react/display-name */

import React from 'react';
import { convertToHTML } from 'draft-convert';

export default contentState =>
  convertToHTML({
    styleToHTML: () => {},
    blockToHTML: (block) => {
      if (block.type === 'atomic') {
        const currentBlock = contentState.getBlockForKey(block.key);
        const entity = contentState.getEntity(currentBlock.getEntityAt(0));
        const type = entity.getType();

        if (type === 'image') {
          return <img src={entity.getData().src} />;
        }

        if (type === 'code-block') {
          return (
            <div>
              <div
                className="liveblog-codeblock-identifier"
                dangerouslySetInnerHTML={{ __html: entity.getData().code }}
              />
            </div>
          );
        }
      }
    },
    entityToHTML: () => {},
  })(contentState);
