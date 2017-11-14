import { convertFromHTML } from 'draft-convert';

export default html =>
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
    },

    htmlToBlock: (nodeName) => {
      if (nodeName === 'img') {
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
