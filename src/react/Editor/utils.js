/* eslint-disable no-param-reassign */

export const getLastIndexOf = (string, characters = []) =>
  characters.reduce((accumulator, character) => {
    return Math.max(string.lastIndexOf(character), accumulator);
  }, -1);

const get = (path, obj, fb = `$\{${path}}`) =>
  path.split('.').reduce((res, key) => res[key] || fb, obj);

export const parseTemplate = (template, map, fallback) =>
  template.replace(/\$\{.+?}/g, (match) => {
    const path = match.substr(2, match.length - 3).trim();
    return get(path, map, fallback);
  });

export const hasEntityAtSelection = (editorState) => {
  const selection = editorState.getSelection();
  if (!selection.getHasFocus()) return false;
  const contentState = editorState.getCurrentContent();
  const block = contentState.getBlockForKey(selection.getStartKey());
  return !!block.getEntityAt(selection.getStartOffset() - 1);
}

export const getTriggerRange = (triggers) => {
  const selection = window.getSelection();
  if (selection.rangeCount === 0) return null;
  const range = selection.getRangeAt(0);
  const text = range.startContainer.textContent.substring(0, range.startOffset);
  if (/\s+$/.test(text)) return null;

  const index = getLastIndexOf(text, triggers);
  if (index === -1) return null;

  return {
    text: text.substring(index),
    start: index,
    end: range.startOffset,
  };
};

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
