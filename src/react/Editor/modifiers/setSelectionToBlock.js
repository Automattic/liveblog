import { SelectionState, EditorState } from 'draft-js';
import DraftOffsetKey from 'draft-js/lib/DraftOffsetKey';

export default (editorState, blockKey) => {
  const offsetKey = DraftOffsetKey.encode(blockKey, 0, 0);
  const node = document.querySelectorAll(`[data-offset-key="${offsetKey}"]`)[0];

  const selection = window.getSelection();
  const range = document.createRange();
  range.setStart(node, 0);
  range.setEnd(node, 0);
  selection.removeAllRanges();
  selection.addRange(range);

  return EditorState.forceSelection(editorState, new SelectionState({
    anchorKey: blockKey,
    anchorOffset: 0,
    focusKey: blockKey,
    focusOffset: 0,
    isBackward: false,
  }));
};
