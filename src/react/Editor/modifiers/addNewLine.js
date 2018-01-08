import { List } from 'immutable';
import {
  Modifier,
  ContentBlock,
  EditorState,
  BlockMapBuilder,
  genKey,
} from 'draft-js';

import removeBlock from './removeBlock';

export default (editorState) => {
  const newEditorState = editorState;
  const contentState = newEditorState.getCurrentContent();
  const selectionState = newEditorState.getSelection();
  const currentBlock = contentState.getBlockForKey(selectionState.getFocusKey());

  /**
   * Draft Convert converts html to blocks and sets the block's text to
   * it's innerHTML. This isn't configurable so to prevent this text
   * being inserted when creating a new line we remove the block and create a
   * new block with empty text.
   */
  const contentStateAfterRemoval = removeBlock(contentState, currentBlock.getKey());

  const fragmentArray = [
    new ContentBlock({
      key: genKey(),
      type: currentBlock.getType(),
      text: ' ',
      characterList: currentBlock.getCharacterList(),
    }),
    new ContentBlock({
      key: genKey(),
      type: 'unstyled',
      text: '',
      characterList: List(),
    }),
  ];

  const fragment = BlockMapBuilder.createFromArray(fragmentArray);

  const withUnstyledBlock = Modifier.replaceWithFragment(
    contentStateAfterRemoval,
    selectionState,
    fragment,
  );

  return EditorState.forceSelection(
    EditorState.push(newEditorState, withUnstyledBlock, 'insert-fragment'),
    withUnstyledBlock.getSelectionAfter(),
  );
};

