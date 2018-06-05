/* eslint-disable no-param-reassign */

/**
 * Returns the highest last index of an array of characters.
 */
export const getLastIndexOf = (string, characters = []) =>
  characters.reduce((accumulator, character) =>
    Math.max(string.lastIndexOf(character), accumulator), -1);

/**
 * Simple templating function that replaces ${data} with a key value.
 */
const get = (path, obj, fb = `$\{${path}}`) =>
  path.split('.').reduce((res, key) => res[key] || fb, obj);

export const parseTemplate = (template, map, fallback) =>
  template.replace(/\$\{.+?}/g, (match) => {
    const path = match.substr(2, match.length - 3).trim();
    return get(path, map, fallback);
  });

/**
 * Returns boolean if selection contains entity
 */
export const hasEntityAtSelection = (editorState, selectionState = false) => {
  const selection = selectionState || editorState.getSelection();
  if (!selection.getHasFocus()) return false;
  const contentState = editorState.getCurrentContent();
  const block = contentState.getBlockForKey(selection.getStartKey());
  const entityKey = block.getEntityAt(selection.getStartOffset() - 1);
  if (!entityKey) return false;
  return contentState.getEntity(entityKey);
};

/**
 * Gets the range of an autocomplete trigger from the trigger character to where
 * the user is typing.
 */
export const getTriggerRange = (triggers) => {
  const selection = window.getSelection();
  if (selection.rangeCount === 0) return null;
  const range = selection.getRangeAt(0);
  const text = range.startContainer.textContent.substring(0, range.startOffset);

  // If the last character is a space bail.
  if (/\s+$/.test(text)) return null;

  const index = getLastIndexOf(text, triggers);
  if (index === -1) return null;

  return {
    text: text.substring(index),
    start: index,
    end: range.startOffset,
  };
};

/**
 * Gets the autocomplete insert range to replace the trigger range with an enitity.
 */
export const getInsertRange = (autocompleteState, editorState) => {
  const currentSelectionState = editorState.getSelection();
  const end = currentSelectionState.getAnchorOffset();
  const anchorKey = currentSelectionState.getAnchorKey();
  const currentContent = editorState.getCurrentContent();
  const currentBlock = currentContent.getBlockForKey(anchorKey);
  const blockText = currentBlock.getText();
  const start = blockText.substring(0, end).lastIndexOf(autocompleteState.trigger);

  return {
    start,
    end,
  };
};

/**
 * Returns a number of the top position of trigger range to poistion the suggestion popover.
 */
export const getTopPosition = (range, parent) => {
  const tempRange = window.getSelection().getRangeAt(0).cloneRange();
  tempRange.setStart(tempRange.startContainer, range.start);
  const parentRect = parent.getBoundingClientRect();
  const rect = tempRange.getBoundingClientRect();
  return (rect.top - parentRect.top) + rect.height;
};

/**
 * If an element is hidden in a scrollable box then make it visible.
 */
export const scrollElementIfNotInView = (childElement, parentElement) => {
  const parentClientRect = parentElement.getBoundingClientRect();
  const parentBottom = parentClientRect.bottom;
  const parentTop = parentClientRect.top;

  const childRect = childElement.getBoundingClientRect();
  const childBottom = childRect.bottom;
  const childTop = childRect.top;
  if (childBottom > parentBottom) parentElement.scrollTop += childRect.height;
  if (childTop < parentTop) parentElement.scrollTop -= childRect.height;
};

/**
 * Returns an array of all the blocks at a selection given a start and end key.
 */
export const getSelectedBlocks = (contentState, anchorKey, focusKey) => {
  const isSameBlock = anchorKey === focusKey;
  const startingBlock = contentState.getBlockForKey(anchorKey);
  const selectedBlocks = [startingBlock];

  if (!isSameBlock) {
    let blockKey = anchorKey;

    while (blockKey !== focusKey) {
      const nextBlock = contentState.getBlockAfter(blockKey);
      selectedBlocks.push(nextBlock);
      blockKey = nextBlock.getKey();
    }
  }

  return selectedBlocks;
};

/**
 * Check if a focusable block is focused/selected
 */
export const focusableBlockIsSelected = (editorState) => {
  const selection = editorState.getSelection();
  if (selection.getAnchorKey() !== selection.getFocusKey()) {
    return false;
  }
  const content = editorState.getCurrentContent();
  const block = content.getBlockForKey(selection.getAnchorKey());
  if (block.getType() === 'atomic') return block;
  return false;
};

/**
 * Get the most suitable image size.
 * @param {object} image
 * @param {string} selectedSize
 */
export const getImageSize = (sizes, defaultSize) => {
  if (!sizes) return '';
  if (sizes[defaultSize]) {
    return sizes[defaultSize].source_url || sizes[defaultSize].url;
  }
  if (sizes.full) {
    return sizes.full.source_url || sizes.full.url;
  }
  return '';
};

/**
 * Map a NamedNodeMap to an object and map the class attribute to className
 * for React.
 * @param {namedNodeMap} attributes
 */
export const namedNodeMapToObject = namedNodeMap =>
  Array.from(namedNodeMap).reduce((object, item) => ({
    ...object,
    [item.name === 'class' ? 'className' : item.name]: item.value,
  }), {});
