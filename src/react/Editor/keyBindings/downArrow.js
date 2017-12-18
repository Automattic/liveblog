import { scrollElementIfNotInView, focusableBlockIsSelected } from '../utils';
import setSelection from '../modifiers/setSelection';

export default (
  editorState,
  autocompleteState,
  onChange,
  setState,
  event,
  suggestionsNode,
  suggestions,
) => {
  if (!autocompleteState) {
    const contentState = editorState.getCurrentContent();
    const currentSelection = editorState.getSelection();
    const startKey = currentSelection.getStartKey();
    const blockAfter = contentState.getBlockAfter(startKey);
    /*
    * If next block or a block is selected we want to handle the
    * selection ourselves so we can add focus to a block if necessary.
    */
    if (
      (blockAfter && blockAfter.getType() === 'atomic') ||
      focusableBlockIsSelected(editorState)
    ) {
      setSelection(editorState, onChange, 'down', event);
      return 'handled';
    }

    return 'not-handled';
  }

  event.preventDefault();
  const selectedIndex = autocompleteState.selectedIndex;
  const newIndex = selectedIndex + 1;

  setState({
    autocompleteState: {
      ...autocompleteState,
      selectedIndex: (selectedIndex >= suggestions.length - 1)
        ? selectedIndex
        : newIndex,
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
