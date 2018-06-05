import { Modifier, SelectionState } from 'draft-js';

export default (contentState, blockKey) => {
  const afterKey = contentState.getKeyAfter(blockKey);
  const afterBlock = contentState.getBlockForKey(afterKey);
  let targetRange;

  if (
    afterBlock &&
    afterBlock.getType() === 'unstyled' &&
    afterBlock.getLength() === 0 &&
    afterBlock === contentState.getBlockMap().last()
  ) {
    targetRange = new SelectionState({
      anchorKey: blockKey,
      anchorOffset: 0,
      focusKey: afterKey,
      focusOffset: 0,
    });
  } else {
    targetRange = new SelectionState({
      anchorKey: blockKey,
      anchorOffset: 0,
      focusKey: blockKey,
      focusOffset: 1,
    });
  }

  const newContentState = Modifier.setBlockType(
    contentState,
    targetRange,
    'unstyled',
  );

  return Modifier.removeRange(newContentState, targetRange, 'backward');
};
