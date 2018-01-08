import Image from './Image';
import Placeholder from './Placeholder';
import CodeBlock from './CodeBlock';
import DragAndFocus from './DragAndFocus';

export default (block, editorState) => {
  if (block.getType() === 'atomic') {
    const contentState = editorState.getCurrentContent();
    const entity = contentState.getEntity(block.getEntityAt(0));
    const type = entity.getType();
    const isFocused = editorState.getSelection().getStartKey() === block.getKey();

    if (type === 'image') {
      return {
        component: DragAndFocus(Image),
        editable: false,
        props: {
          isFocused,
        },
      };
    }
    if (type === 'code-block') {
      return {
        component: DragAndFocus(CodeBlock),
        editable: false,
        props: {
          isFocused,
        },
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

