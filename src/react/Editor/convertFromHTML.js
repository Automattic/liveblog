/* eslint-disable consistent-return */
import { convertFromHTML } from 'draft-convert';

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

      if (nodeName === 'img') {
        return createEntity(
          'image',
          'IMMUTABLE',
          { src: node.src },
        );
      }

      if (node.classList && node.classList.contains('liveblog-codeblock-identifier')) {
        return createEntity(
          'code-block',
          'IMMUTABLE',
          {
            code: node.innerHTML,
            toggleReadOnly: extraData.toggleReadOnly,
          },
        );
      }
    },

    htmlToBlock: (nodeName, node) => {
      if (
        nodeName === 'img' ||
        (node.classList && node.classList.contains('liveblog-codeblock-identifier'))
      ) {
        return 'atomic';
      }
    },

    textToEntity: (text, createEntity) => {
      const result = [];

      const emojis = window.liveblog_settings.autocomplete[1].data;
      const cdn = window.liveblog_settings.autocomplete[1].cdn;

      text.replace(/:(\w+):/g, (match, name, offset) => {
        const emoji = emojis.filter(x =>
          match.replace(/:/g, '') === x.key.toString(),
        )[0];

        const entityKey = createEntity(
          ':',
          'IMMUTABLE',
          {
            trigger: ':',
            suggestion: { image: emoji.image },
            extraData: { cdn },
          },
        );

        result.push({
          entity: entityKey,
          offset,
          length: match.length,
          result: match,
        });
      });

      return result;
    },

  })(html);
