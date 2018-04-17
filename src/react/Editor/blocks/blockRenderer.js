import Image from './Image';
import Placeholder from './Placeholder';
import CodeBlock from './CodeBlock';
import CreateBlock from './CreateBlockHOC';
import Media from './Media';

export default (block, editorState, onChange) => {
  if (block.getType() === 'atomic') {
    const contentState = editorState.getCurrentContent();
    const entity = contentState.getEntity(block.getEntityAt(0));
    const type = entity.getType();
    const isFocused = editorState.getSelection().getStartKey() === block.getKey();

    if (type === 'image') {
      return {
        component: CreateBlock(Image, editorState, onChange),
        editable: false,
        props: {
          isFocused,
        },
      };
    }
    if (type === 'code-block') {
      return {
        component: CreateBlock(CodeBlock, editorState, onChange),
        editable: false,
        props: {
          isFocused,
        },
      };
    }
    if (type === 'media') {
      return {
        component: CreateBlock(Media, editorState, onChange),
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

