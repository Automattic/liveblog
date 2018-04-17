import React from 'react';
import PropTypes from 'prop-types';

const Image = ({ contentState, block }) => {
  const { src } = contentState.getEntity(block.getEntityAt(0)).getData();

  return (
    <img
      src={src}
      role="presentation"
    />
  );
};

Image.propTypes = {
  contentState: PropTypes.object,
  block: PropTypes.object,
};

export default Image;
