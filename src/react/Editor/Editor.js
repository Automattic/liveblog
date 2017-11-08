/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';

import {
  EditorState,
  Editor,
  CompositeDecorator,
  Modifier,
} from 'draft-js';

import {
  parseTemplate,
  getTriggerRange,
  getInsertRange,
  hasEntityAtSelection,
  scrollElementIfNotInView,
} from './utils';

import Toolbar, { LinkComponent, findLinkEntities } from './Toolbar';
import Suggestions from './Suggestions';

export const decorators = new CompositeDecorator([{
  strategy: findLinkEntities,
  component: LinkComponent,
}]);

class EditorWrapper extends Component {
  constructor(props) {
    super(props);

    this.state = {
      autocompleteState: null,
    };
  }

  updateEditorState(editorState) {
    const { onChange, resetSuggestions, suggestions } = this.props;

    onChange(editorState);
    // Wait until editor has been updated.
    setTimeout(() => {
      // Check for any triggers.
      const autocompleteState = this.getAutocompleteState();
      // Prevent any bugs from happing when two commands are inputted next to each other.
      if (
        autocompleteState &&
        this.state.autocompleteState &&
        suggestions.length > 0 &&
        this.state.autocompleteState.trigger !== autocompleteState.trigger
      ) {
        resetSuggestions();
      }
      // Reset suggestions on state refresh.
      if (!autocompleteState && suggestions.length > 0) resetSuggestions();
      // Update the autocomplete state, will be null if no triggers.
      this.setState({ autocompleteState });
    }, 0);
  }

  onDownArrow(e, originalHandler) {
    const { autocompleteState } = this.state;
    const { suggestions } = this.props;

    if (!autocompleteState) {
      if (originalHandler) originalHandler(e);
      return;
    }

    e.preventDefault();

    const selectedIndex = autocompleteState.selectedIndex;
    const newIndex = selectedIndex + 1;

    this.setState({
      autocompleteState: {
        ...autocompleteState,
        selectedIndex: (selectedIndex >= suggestions.length - 1)
          ? selectedIndex
          : newIndex,
      },
    });

    const selectedSuggestionDomNode = this.suggestions[`item${newIndex}`];
    if (!selectedSuggestionDomNode) return;

    scrollElementIfNotInView(
      selectedSuggestionDomNode,
      this.suggestions.list,
    );
  }

  onUpArrow(e, originalHandler) {
    const { autocompleteState } = this.state;

    if (!autocompleteState) {
      if (originalHandler) originalHandler(e);
      return;
    }

    const selectedIndex = autocompleteState.selectedIndex;
    const newIndex = Math.max(selectedIndex - 1, 0);

    e.preventDefault();

    this.setState({
      autocompleteState: {
        ...autocompleteState,
        selectedIndex: newIndex,
      },
    });

    const selectedSuggestionDomNode = this.suggestions[`item${newIndex}`];
    if (!selectedSuggestionDomNode) return;

    scrollElementIfNotInView(
      selectedSuggestionDomNode,
      this.suggestions.list,
    );
  }

  onEscape(e, originalHandler) {
    const { autocompleteState } = this.state;

    if (!autocompleteState) {
      if (originalHandler) originalHandler(e);
      return;
    }

    e.preventDefault();

    this.setState({
      autocompleteState: null,
    });
  }

  handleReturn(e) {
    const { autocompleteState } = this.state;
    if (!autocompleteState) return false;

    e.preventDefault();
    this.turnSuggestionIntoEntity();
    return true;
  }

  getAutocompleteState() {
    const { autocompleteConfig, editorState } = this.props;

    // If we are already selecting an entity bail, we don't need to trigger anything.
    if (hasEntityAtSelection(editorState)) return null;

    // Get the trigger range from when the trigger is fired to where the user is typing.
    const range = getTriggerRange(autocompleteConfig.map(x => x.trigger));
    if (!range) return null;
    const trigger = range.text.charAt(0);
    const config = autocompleteConfig.filter(x => x.trigger === trigger)[0];

    // Any data we need for autocompleting should be passed to state here as this will
    // always be the reference to the current autocomplete state.
    return {
      ...range,
      trigger,
      displayKey: config.displayKey,
      replaceText: config.replaceText,
      name: config.name,
      searchText: range.text.slice(1, range.text.length),
      selectedIndex: 0,
    };
  }

  turnSuggestionIntoEntity(index = false) {
    const { autocompleteState } = this.state;
    const { editorState, suggestions } = this.props;

    // Get the current selected suggestion and bail if it doesn't exist.
    let suggestionIndex = autocompleteState.selectedIndex;
    if (index || index === 0) suggestionIndex = index;
    const suggestion = suggestions[suggestionIndex];
    if (!suggestion) return;

    // Get the selection we want to replace with an entity.
    const { start, end } = getInsertRange(autocompleteState, editorState);
    const currentSelectionState = editorState.getSelection();
    const contentState = editorState.getCurrentContent();
    const selection = currentSelectionState.merge({
      anchorOffset: start,
      focusOffset: end,
    });

    // Create an enitity to place within the content.
    const contentStateWithEntity = contentState.createEntity(
      autocompleteState.trigger,
      'IMMUTABLE',
      {
        trigger: autocompleteState.trigger,
        suggestion,
      },
    );

    // Replace with enitity using template from config.
    const entityKey = contentStateWithEntity.getLastCreatedEntityKey();
    const replaceText = autocompleteState.displayKey
      ? suggestion[autocompleteState.displayKey]
      : suggestion;

    let newContentState = Modifier.replaceText(
      contentState,
      selection,
      autocompleteState.replaceText.replace('$', replaceText),
      null,
      entityKey,
    );

    // If its at the end we insert it with a space after for a nicer ux.
    const blockKey = selection.getAnchorKey();
    const blockSize = contentState.getBlockForKey(blockKey).getLength();

    if (blockSize === end) {
      newContentState = Modifier.insertText(
        newContentState,
        newContentState.getSelectionAfter(),
        ' ',
      );
    }

    // Create the new editor state with the entity and update accordingly.
    const newEditorState = EditorState.push(
      editorState,
      newContentState,
      `insert-${autocompleteState.trigger}`,
    );

    this.updateEditorState(
      EditorState.forceSelection(
        newEditorState,
        newContentState.getSelectionAfter(),
      ),
    );
  }

  renderTemplate(item) {
    const { autocompleteConfig } = this.props;
    const { trigger } = this.state.autocompleteState;
    const config = autocompleteConfig.filter(x => x.trigger === trigger)[0];
    if (!config.suggestionTemplate) return item;
    return parseTemplate(config.suggestionTemplate, item);
  }

  render() {
    const {
      autocompleteState,
    } = this.state;

    const {
      editorState,
      onChange,
      suggestions,
      onSearch,
    } = this.props;

    return (
      <div style={{ position: 'relative' }}>
        <Toolbar
          editorState={editorState}
          onChange={onChange}
        />
        <Editor
          editorState={editorState}
          onChange={this.updateEditorState.bind(this)}
          ref={ref => this.editor = ref}
          onDownArrow={this.onDownArrow.bind(this)}
          onUpArrow={this.onUpArrow.bind(this)}
          onEscape={this.onEscape.bind(this)}
          handleReturn={this.handleReturn.bind(this)}
          refs={ref => this.editor = ref}
        />
        <Suggestions
          turnIntoEntity={index => this.turnSuggestionIntoEntity(index)}
          autocompleteState={autocompleteState}
          suggestions={suggestions}
          renderTemplate={item => this.renderTemplate(item)}
          onSearch={(trigger, text) => onSearch(trigger, text)}
          setSuggestionIndex={i =>
            this.setState({
              autocompleteState: {
                ...autocompleteState,
                selectedIndex: i,
              },
            })
          }
          ref={node => this.suggestions = node}
        />
      </div>
    );
  }
}

EditorWrapper.propTypes = {
  onChange: PropTypes.func,
  resetSuggestions: PropTypes.func,
  suggestions: PropTypes.array,
  autocompleteConfig: PropTypes.array,
  editorState: PropTypes.object,
  onSearch: PropTypes.func,
};

export default EditorWrapper;
