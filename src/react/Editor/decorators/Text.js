import React from 'react';
import PropTypes from 'prop-types';

const Text = ({ children, entityKey, contentState }) => {
  const { nodeName, attributes } = contentState.getEntity(entityKey).getData();

  return React.createElement(
    nodeName,
    attributes,
    children,
  );
};

Text.propTypes = {
  children: PropTypes.any,
  contentState: PropTypes.object,
  entityKey: PropTypes.string,
};

export default Text;

export const findTextEntities = (contentBlock, callback, contentState) => {
  contentBlock.findEntityRanges(
    (character) => {
      const entityKey = character.getEntity();
      return (
        entityKey !== null &&
        contentState.getEntity(entityKey).getType() === 'TEXT'
      );
    },
    callback,
  );
};
