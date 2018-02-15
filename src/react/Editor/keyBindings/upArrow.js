import { scrollElementIfNotInView, focusableBlockIsSelected } from '../utils';
import setSelection from '../modifiers/setSelection';

export default (
  editorState,
  autocompleteState,
  onChange,
  setState,
  event,
  suggestionsNode,
) => {
  if (!autocompleteState) {
    const contentState = editorState.getCurrentContent();
    const currentSelection = editorState.getSelection();
    const startKey = currentSelection.getStartKey();
    const blockBefore = contentState.getBlockBefore(startKey);

    /*
    * If previous block or a block is selected we want to handle the
    * selection ourselves so we can add focus to a block if necessary.
    */
    if (
      (blockBefore && blockBefore.getType() === 'atomic') ||
      focusableBlockIsSelected(editorState)
    ) {
      setSelection(editorState, onChange, 'up', event);
      return 'handled';
    }

    return 'not-handled';
  }

  event.preventDefault();

  const selectedIndex = autocompleteState.selectedIndex;
  const newIndex = Math.max(selectedIndex - 1, 0);

  setState({
    autocompleteState: {
      ...autocompleteState,
      selectedIndex: newIndex,
    },
  });

  const selectedSuggestionDomNode = suggestionsNode[`item${newIndex}`];
  if (!selectedSuggestionDomNode) return 'handled';

  scrollElementIfNotInView(
    selectedSuggestionDomNode,
    suggestionsNode.list,
  );

  return 'handled';
};
