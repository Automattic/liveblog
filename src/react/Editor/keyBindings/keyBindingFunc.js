import { getDefaultKeyBinding, EditorState } from 'draft-js';
import { focusableBlockIsSelected } from '../utils';
import setSelection from '../modifiers/setSelection';
import removeBlock from '../modifiers/removeBlock';

export default (event, editorState, onChange) => {
  const isLeftPress = event.keyCode === 37;
  const isRightPress = event.keyCode === 39;
  const isBackspacePress = event.keyCode === 8;

  if (focusableBlockIsSelected(editorState)) {
    if (isLeftPress) {
      setSelection(editorState, onChange, 'up', event);
      return 'handled';
    }

    if (isRightPress) {
      setSelection(editorState, onChange, 'down', event);
      return 'handled';
    }

    if (isBackspacePress) {
      const content = editorState.getCurrentContent();
      const block = content.getBlockForKey(editorState.getSelection().getAnchorKey());
      const contentAfterRemove = removeBlock(content, block.getKey());

      onChange(
        EditorState.forceSelection(
          EditorState.push(editorState, contentAfterRemove, 'remove-block'),
          contentAfterRemove.getSelectionAfter(),
        ),
      );

      return 'handled';
    }

    return 'handled';
  }

  if (isLeftPress || isBackspacePress) {
    const selection = editorState.getSelection();
    const anchorKey = selection.getStartKey();
    const content = editorState.getCurrentContent();
    const blockBefore = content.getBlockBefore(anchorKey);
    if (blockBefore && selection.getAnchorOffset() === 0 && blockBefore.getType() === 'atomic') {
      setSelection(editorState, onChange, 'up', event);
      return 'handled';
    }
  }

  if (isRightPress) {
    const selection = editorState.getSelection();
    const anchorKey = selection.getAnchorKey();
    const currentBlock = editorState.getCurrentContent().getBlockForKey(anchorKey);
    const blockAfter = editorState.getCurrentContent().getBlockAfter(anchorKey);
    if (
      blockAfter &&
      currentBlock.getLength() === selection.getFocusOffset() &&
      blockAfter.getType() === 'atomic'
    ) {
      setSelection(editorState, onChange, 'down', event);
      return 'handled';
    }
  }

  return getDefaultKeyBinding(event);
};

