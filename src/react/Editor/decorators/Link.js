import React from 'react';
import PropTypes from 'prop-types';

const Link = ({ entityKey, children, contentState }) => {
  const { url } = contentState.getEntity(entityKey).getData();
  return (
    <a href={url}>{children}</a>
  );
};

Link.propTypes = {
  entityKey: PropTypes.any,
  children: PropTypes.any,
  contentState: PropTypes.any,
};

export default Link;

export const findLinkEntities = (contentBlock, callback, contentState) => {
  contentBlock.findEntityRanges(
    (character) => {
      const entityKey = character.getEntity();
      return (
        entityKey !== null &&
        contentState.getEntity(entityKey).getType() === 'LINK'
      );
    },
    callback,
  );
};
