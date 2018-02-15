import {
  EditorState,
  AtomicBlockUtils,
} from 'draft-js';

import moveBlock from './moveBlock';
import addNewLine from './addNewLine';

import {
  focusableBlockIsSelected,
} from '../utils';

export default (editorState, selection = false, data = {}, name) => {
  let newEditorState = editorState;

  if (focusableBlockIsSelected(editorState)) {
    newEditorState = addNewLine(editorState);
  }

  const contentState = newEditorState.getCurrentContent();
  const contentStateWithEntity = contentState.createEntity(
    name,
    'IMMUTABLE',
    data,
  );

  const entityKey = contentStateWithEntity.getLastCreatedEntityKey();

  newEditorState = AtomicBlockUtils.insertAtomicBlock(
    newEditorState,
    entityKey,
    ' ',
  );

  if (!selection) {
    return EditorState.forceSelection(
      newEditorState,
      newEditorState.getCurrentContent().getSelectionAfter(),
    );
  }

  const newAtomicBlock = newEditorState
    .getCurrentContent()
    .getBlockMap()
    .find(x => x.getEntityAt(0) === entityKey);

  return moveBlock(newEditorState, newAtomicBlock.getKey(), selection);
};
