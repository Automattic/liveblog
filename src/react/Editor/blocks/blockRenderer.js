import Image from './Image';
import Placeholder from './Placeholder';
import CodeBlock from './CodeBlock';
import DragAndFocus from './DragAndFocusHOC';
import setSelectionToBlock from '../modifiers/setSelectionToBlock';

export default (block, editorState, onChange) => {
  if (block.getType() === 'atomic') {
    const blockKey = block.getKey();
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
          setSelectionToBlock: () => {
            onChange(
              setSelectionToBlock(editorState, blockKey),
            );
          },
        },
      };
    }
    if (type === 'code-block') {
      return {
        component: DragAndFocus(CodeBlock),
        editable: false,
        props: {
          isFocused,
          setSelectionToBlock: () => {
            onChange(
              setSelectionToBlock(editorState, blockKey),
            );
          },
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

