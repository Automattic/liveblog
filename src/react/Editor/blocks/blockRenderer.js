import Image from './Image';
import Placeholder from './Placeholder';

export default (block, editorState) => {
  if (block.getType() === 'atomic') {
    const contentState = editorState.getCurrentContent();
    const entity = contentState.getEntity(block.getEntityAt(0));
    const type = entity.getType();
    if (type === 'image') {
      return {
        component: Image,
        editable: false,
      };
    }
    if (type === 'placeholder') {
      return {
        component: Placeholder,
        editable: false,
      };
    }
  }

  return null;
};

