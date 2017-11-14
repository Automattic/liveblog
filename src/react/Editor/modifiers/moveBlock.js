import { EditorState, SelectionState } from 'draft-js';

import removeBlock from './removeBlock';
import addBlock from './addBlock';

export default (editorState, blockKey, selection) => {
  const contentState = editorState.getCurrentContent();
  const block = contentState.getBlockForKey(blockKey);
  const entity = contentState.getEntity(block.getEntityAt(0));

  const contentStateAfterInsert = addBlock(
    editorState,
    selection,
    block.getType(),
    entity.data,
    entity.type,
  );

  const contentStateAfterRemove = removeBlock(contentStateAfterInsert, blockKey);

  const newState = EditorState.push(editorState, contentStateAfterRemove, 'move-block');

  const newSelection = new SelectionState({
    anchorKey: blockKey,
    anchorOffset: 0,
    focusKey: blockKey,
    focusOffset: 0,
  });

  return EditorState.forceSelection(
    newState,
    newSelection,
  );
};

