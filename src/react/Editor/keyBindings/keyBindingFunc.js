import { getDefaultKeyBinding } from 'draft-js';
import { focusableBlockIsSelected } from '../utils';
import setSelection from '../modifiers/setSelection';

export default (event, editorState, onChange) => {
  const isLeftPress = event.keyCode === 37;
  const isRightPress = event.keyCode === 39;

  if (focusableBlockIsSelected(editorState)) {
    if (isLeftPress) setSelection(editorState, onChange, 'up', event);
    if (isRightPress) setSelection(editorState, onChange, 'down', event);
  }

  if (isLeftPress) {
    const selection = editorState.getSelection();
    const anchorKey = selection.getAnchorKey();
    const blockBefore = editorState.getCurrentContent().getBlockBefore(anchorKey);
    // only if the selection caret is a the left most position
    if (blockBefore && selection.getAnchorOffset() === 0 && blockBefore.getType() === 'atomic') {
      setSelection(editorState, onChange, 'up', event);
    }
  }

  if (isRightPress) {
    const selection = editorState.getSelection();
    const anchorKey = selection.getAnchorKey();
    const currentBlock = editorState.getCurrentContent().getBlockForKey(anchorKey);
    const blockAfter = editorState.getCurrentContent().getBlockAfter(anchorKey);
    // only if the selection caret is a the left most position
    if (
      blockAfter &&
      currentBlock.getLength() === selection.getFocusOffset() &&
      blockAfter.getType() === 'atomic'
    ) {
      setSelection(editorState, onChange, 'down', event);
    }
  }

  return getDefaultKeyBinding(event);
};

