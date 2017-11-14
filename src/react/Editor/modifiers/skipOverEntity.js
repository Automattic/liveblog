import { EditorState } from 'draft-js';

export default (editorState, entity) => {
  const selection = editorState.getSelection();
  const { startOffset, endOffset } = entity.getData();
  const currentSelection = selection.getStartOffset();
  let position = false;

  if (currentSelection === (startOffset + 1)) {
    position = endOffset;
  }

  if (currentSelection === (endOffset - 1)) {
    position = startOffset;
  }

  if (position || position === 0) {
    return EditorState.forceSelection(editorState, selection.merge({
      anchorOffset: position,
      focusOffset: position,
    }));
  }

  return editorState;
};
