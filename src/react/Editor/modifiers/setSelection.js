
import { SelectionState, EditorState } from 'draft-js';
import DraftOffsetKey from 'draft-js/lib/DraftOffsetKey';

export default (editorState, onChange, direction, event) => {
  const anchorKey = editorState.getSelection().getAnchorKey();
  const block = direction === 'up'
    ? editorState.getCurrentContent().getBlockBefore(anchorKey)
    : editorState.getCurrentContent().getBlockAfter(anchorKey);

  if (block && block.get('key') === anchorKey) {
    return;
  }

  if (block) {
    event.preventDefault();

    const offsetKey = DraftOffsetKey.encode(block.getKey(), 0, 0);
    // Set the native selection to the node so the selection is not in the text
    const node = document.querySelector(`[data-offset-key="${offsetKey}"]`);
    const selection = window.getSelection();
    const range = document.createRange();
    range.setStart(node, 0);
    range.setEnd(node, 0);
    selection.removeAllRanges();
    selection.addRange(range);

    const offset = direction === 'up'
      ? block.getLength()
      : 0;

    onChange(
      EditorState.forceSelection(
        editorState,
        new SelectionState({
          anchorKey: block.getKey(),
          anchorOffset: offset,
          focusKey: block.getKey(),
          focusOffset: offset,
          isBackward: false,
        }),
      ),
    );
  }
};
