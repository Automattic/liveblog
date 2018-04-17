// @todo tidy up this file

import { List, Repeat } from 'immutable';
import {
  Modifier,
  CharacterMetadata,
  BlockMapBuilder,
  ContentBlock,
  genKey,
} from 'draft-js';

import { hasEntityAtSelection } from '../utils';

export default function (editorState, selection, type, data, entityType, text = ' ') {
  let contentState = editorState.getCurrentContent();
  const currentSelectionState = selection;

  if (!hasEntityAtSelection(editorState, selection)) {
    contentState = Modifier.removeRange(
      contentState,
      currentSelectionState,
      'backward',
    );
  }

  // deciding on the postion to split the text
  const targetSelection = contentState.getSelectionAfter();
  const blockKeyForTarget = targetSelection.get('focusKey');
  const block = contentState.getBlockForKey(blockKeyForTarget);
  let insertionTargetSelection;
  let insertionTargetBlock;

  /**
  *  In case there are no characters or entity or the selection is at the start it
  * is safe to insert the block in the current block.
  * Otherwise a new block is created (the block is always its own block)
  */
  const isEmptyBlock = block.getLength() === 0 && block.getEntityAt(0) === null;
  const selectedFromStart = currentSelectionState.getStartOffset() === 0;
  if (isEmptyBlock || selectedFromStart) {
    insertionTargetSelection = targetSelection;
    insertionTargetBlock = contentState;
  } else {
    // the only way to insert a new seems to be by splitting an existing in to two
    insertionTargetBlock = Modifier.splitBlock(contentState, targetSelection);
    // the position to insert our blocks
    insertionTargetSelection = insertionTargetBlock.getSelectionAfter();
  }

  const newContentStateAfterSplit = Modifier.setBlockType(
    insertionTargetBlock,
    insertionTargetSelection,
    type,
  );

  // creating a new ContentBlock including the entity with data
  // Entity will be created with a specific type,
  // if defined, else will fall back to the ContentBlock type
  const contentStateWithEntity = newContentStateAfterSplit.createEntity(
    entityType || type,
    'IMMUTABLE',
    { ...data },
  );

  const entityKey = contentStateWithEntity.getLastCreatedEntityKey();
  const charData = CharacterMetadata.create({ entity: entityKey });

  const fragmentArray = [
    new ContentBlock({
      key: genKey(),
      type,
      text,
      characterList: List(Repeat(charData, text.length || 1)),
    }),
  ];

  if (!isEmptyBlock) {
    fragmentArray.push(
      new ContentBlock({
        key: genKey(),
        type: 'unstyled',
        text: '',
        characterList: List(),
      }),
    );
  }

  // create fragment containing the two content blocks
  const fragment = BlockMapBuilder.createFromArray(fragmentArray);

  // replace the contentblock we reserved for our insert
  return Modifier.replaceWithFragment(
    newContentStateAfterSplit,
    insertionTargetSelection,
    fragment,
  );
}
