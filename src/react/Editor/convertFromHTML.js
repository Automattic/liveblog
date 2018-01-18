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

      if (node.id && node.id.includes('liveblog-codeblock-identifier-')) {
        return createEntity(
          'code-block',
          'IMMUTABLE',
          {
            code: node.innerHTML,
            title: node.id.replace('liveblog-codeblock-identifier-', '').replace('-', ' '),
            setReadOnly: extraData.setReadOnly,
          },
        );
      }
    },

    htmlToBlock: (nodeName, node) => {
      if (nodeName === 'p' && node.innerHTML === '') {
        return false;
      }

      if (
        nodeName === 'img' ||
        (node.id && node.id.includes('liveblog-codeblock-identifier-'))
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
