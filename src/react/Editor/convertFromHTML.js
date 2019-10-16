/* eslint-disable consistent-return */
import { convertFromHTML } from 'draft-convert';
import { CODE_BLOCK_TAGS, IGNORED_TAGS, TEXT_TAGS } from './HTMLtags';
import { namedNodeMapToObject } from './utils';

export default (html, extraData) =>
  convertFromHTML({
    htmlToEntity: (nodeName, node, createEntity) => {
      if (nodeName === 'a') {
        return createEntity(
          'LINK',
          'MUTABLE',
          { url: node.href },
        );
      }

      if (TEXT_TAGS.includes(nodeName)) {
        return createEntity(
          'TEXT',
          'MUTABLE',
          {
            nodeName,
            attributes: namedNodeMapToObject(node.attributes),
          },
        );
      }

      if (nodeName === 'img') {
        return createEntity(
          'media',
          'IMMUTABLE',
          {
            setReadOnly: extraData.setReadOnly,
            image: node.src,
            edit: false,
            handleImageUpload: extraData.handleImageUpload,
            defaultImageSize: extraData.defaultImageSize,
          },
        );
      }

      const isHTMLBlock = node.id && node.id.includes('liveblog-codeblock-identifier-');

      if (
        isHTMLBlock ||
        CODE_BLOCK_TAGS.includes(nodeName)
      ) {
        return createEntity(
          'code-block',
          'IMMUTABLE',
          {
            code: isHTMLBlock ? node.innerHTML : node.outerHTML,
            title: isHTMLBlock
              ? node.id.replace('liveblog-codeblock-identifier-', '').replace('-', ' ')
              : nodeName,
            setReadOnly: extraData.setReadOnly,
          },
        );
      }
    },

    htmlToBlock: (nodeName, node) => {
      if (nodeName === 'p' && node.innerHTML === '') {
        return false;
      }

      if (IGNORED_TAGS.includes(nodeName)) {
        return false;
      }

      if (
        nodeName === 'img' ||
        (node.id && node.id.includes('liveblog-codeblock-identifier-')) ||
        CODE_BLOCK_TAGS.includes(nodeName)
      ) {
        return 'atomic';
      }
    },

  })(html);
