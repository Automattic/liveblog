import React from 'react';
import PropTypes from 'prop-types';

const Emoji = ({ children, entityKey, contentState }) => {
  const { suggestion, extraData } = contentState.getEntity(entityKey).getData();
  const backgroundImage = `url(${extraData.cdn}${suggestion.image}.png)`;

  return (
    <span className="liveblog-inline-emoji" style={{ backgroundImage }}>
      {children}
    </span>
  );
};

Emoji.propTypes = {
  children: PropTypes.any,
  contentState: PropTypes.object,
  entityKey: PropTypes.string,
};

export default Emoji;

export const findEmojiEntities = (contentBlock, callback, contentState) => {
  contentBlock.findEntityRanges(
    (character) => {
      const entityKey = character.getEntity();
      return (
        entityKey !== null &&
        contentState.getEntity(entityKey).getType() === ':'
      );
    },
    callback,
  );
};
