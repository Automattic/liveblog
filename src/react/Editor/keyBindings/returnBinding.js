import { focusableBlockIsSelected } from '../utils';
import addNewLine from '../modifiers/addNewLine';

export default (
  editorState,
  autocompleteState,
  onChange,
  event,
  suggestions,
  turnSuggestionIntoEntity,
) => {
  if (
    autocompleteState ||
    (suggestions.length > 0 && suggestions[autocompleteState.selectedIndex])
  ) {
    event.preventDefault();
    turnSuggestionIntoEntity();
    return 'handled';
  }

  if (focusableBlockIsSelected(editorState)) {
    onChange(
      addNewLine(editorState),
    );
    return 'handled';
  }

  return 'not-handled';
};
