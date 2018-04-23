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
                id={`liveblog-codeblock-identifier-${entity.getData().title.replace(/\s+/g, '-')}`}
                dangerouslySetInnerHTML={{ __html: entity.getData().code }}
              />
            </div>
          );
        }

        if (type === 'media') {
          return <img src={entity.getData().image} />;
        }
      }
      if (block.type === 'unordered-list-item') {
        return {
          start: '<li>',
          end: '</li>',
          nestStart: '<ul>',
          nestEnd: '</ul>',
        };
      }
      if (block.type === 'ordered-list-item') {
        return {
          start: '<li>',
          end: '</li>',
          nestStart: '<ol>',
          nestEnd: '</ol>',
        };
      }
      if (block.type === 'unstyled') {
        return <p />;
      }
      return <span />;
    },
    entityToHTML: (entity, originalText) => {
      if (entity.type === 'LINK') {
        return <a href={entity.data.url}>{originalText}</a>;
      }
      if (entity.type === 'TEXT') {
        return React.createElement(
          entity.data.nodeName,
          entity.data.attributes,
          originalText,
        );
      }
      return originalText;
    },
  })(contentState);
