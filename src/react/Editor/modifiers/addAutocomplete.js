import {
  EditorState,
  Modifier,
} from 'draft-js';

import {
  getInsertRange,
} from '../utils';

export default (editorState, autocompleteState, suggestion, extraData) => {
  // Get the selection we want to replace with an entity.
  const { start, end } = getInsertRange(autocompleteState, editorState);

  const currentSelectionState = editorState.getSelection();
  const selection = currentSelectionState.merge({
    anchorOffset: start,
    focusOffset: end,
  });

  const contentState = editorState.getCurrentContent();

  let replaceText = autocompleteState.displayKey
    ? suggestion[autocompleteState.displayKey]
    : suggestion;

  replaceText = autocompleteState.replaceText.replace('$', replaceText);

  // Create an enitity to place within the content.
  const contentStateWithEntity = contentState.createEntity(
    autocompleteState.trigger,
    'IMMUTABLE',
    {
      trigger: autocompleteState.trigger,
      suggestion,
      extraData,
      startOffset: start,
      endOffset: start + replaceText.length,
    },
  );

  // Replace with enitity using template from config.
  const entityKey = contentStateWithEntity.getLastCreatedEntityKey();
  let newContentState = Modifier.replaceText(
    contentState,
    selection,
    replaceText,
    null,
    entityKey,
  );

  // If its at the end we insert it with a space after for a nicer ux.
  const blockKey = selection.getAnchorKey();
  const blockSize = contentState.getBlockForKey(blockKey).getLength();

  if (blockSize === end) {
    newContentState = Modifier.insertText(
      newContentState,
      newContentState.getSelectionAfter(),
      ' ',
    );
  }

  const newEditorState = EditorState.push(
    editorState,
    newContentState,
    `insert-${autocompleteState.trigger}`,
  );

  // Return the new editor state with the entity.
  return EditorState.forceSelection(
    newEditorState,
    newContentState.getSelectionAfter(),
  );
};
