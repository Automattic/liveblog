/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';

import 'draft-js/dist/Draft.css';

import {
  Editor,
  RichUtils,
} from 'draft-js';

import {
  parseTemplate,
  getTriggerRange,
  hasEntityAtSelection,
  scrollElementIfNotInView,
  getTopPosition,
  uniqueHTMLId,
} from './utils';

import addAutocomplete from './modifiers/addAutocomplete';
import addImage from './modifiers/addImage';
import moveBlock from './modifiers/moveBlock';
import addPlaceholder from './modifiers/addPlaceholder';
import skipOverEntity from './modifiers/skipOverEntity';

import blockRenderer from './blocks/blockRenderer';

import Toolbar from './Toolbar';
import Suggestions from './Suggestions';

class EditorWrapper extends Component {
  constructor(props) {
    super(props);

    this.state = {
      autocompleteState: null,
    };
  }

  componentWillMount() {
    this.inputId = uniqueHTMLId('imageUpload');
  }

  /**
   * Hook into updating the editor state so we can check for any triggers
   * for the autocomplete every time the editor state is updated.
   */
  updateEditorState(editorState) {
    const { onChange, resetSuggestions, suggestions } = this.props;
    onChange(editorState);

    // Wait until the state has been updated.
    setTimeout(() => {
      // Fix for selection getting 'stuck' in emoji as you have to pass through each character.
      const entity = hasEntityAtSelection(editorState);
      if (entity) {
        if ([':'].includes(entity.getType())) {
          onChange(
            skipOverEntity(editorState, entity),
          );
        }
      }

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

  /**
   * Down Arrow handler to scroll through suggestion list if it exists
   */
  onDownArrow(e) {
    const { autocompleteState } = this.state;
    const { suggestions } = this.props;

    if (!autocompleteState) return 'not-handled';

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
    if (!selectedSuggestionDomNode) return 'handled';

    scrollElementIfNotInView(
      selectedSuggestionDomNode,
      this.suggestions.list,
    );

    return 'handled';
  }

  /**
   * Up Arrow handler to scroll through suggestion list if it exists
   */
  onUpArrow(e) {
    const { autocompleteState } = this.state;

    if (!autocompleteState) return 'not-handled';

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
    if (!selectedSuggestionDomNode) return 'handled';

    scrollElementIfNotInView(
      selectedSuggestionDomNode,
      this.suggestions.list,
    );

    return 'handled';
  }

  /**
   * Escape handler to exit suggestion list if it exists
   */
  onEscape(e) {
    const { autocompleteState } = this.state;

    if (!autocompleteState) return 'not-handled';

    e.preventDefault();
    this.setState({ autocompleteState: null });
    return 'handled';
  }

  /**
   * Return handler to choose suggestion from list if it exists
   */
  handleReturn(e) {
    const { autocompleteState } = this.state;
    const { suggestions } = this.props;

    if (
      !autocompleteState ||
      !suggestions[autocompleteState.selectedIndex]
    ) {
      return 'not-handled';
    }

    e.preventDefault();
    this.turnSuggestionIntoEntity();
    return 'handled';
  }

  /**
   * Handle getting the autocompletestate.
   */
  getAutocompleteState() {
    const { autocompleteConfig, editorState } = this.props;
    // If we are already selecting an entity bail, we don't need to trigger anything.
    if (hasEntityAtSelection(editorState)) return null;
    // Get the trigger range from when the trigger is fired to where the user is typing.
    const range = getTriggerRange(autocompleteConfig.map(x => x.trigger));
    // If range doesn't exist or contains a space then bail.
    if (!range || /\s/g.test(range.text)) return null;

    const trigger = range.text.charAt(0);
    const config = autocompleteConfig.filter(x => x.trigger === trigger)[0];
    // Any data we need for autocompleting should be passed to state here as this will
    // always be the reference to the current autocomplete state.
    return {
      ...range,
      trigger,
      top: getTopPosition(range, this.editor.refs.editorContainer),
      displayKey: config.displayKey,
      replaceText: config.replaceText,
      name: config.name,
      searchText: range.text.slice(1, range.text.length),
      selectedIndex: 0,
    };
  }

  /**
   * If there is a selected entity we replace the current autocomplete range with
   * a suggestion.
   */
  turnSuggestionIntoEntity(index = false) {
    const { autocompleteState } = this.state;
    const { editorState, suggestions, autocompleteConfig } = this.props;
    // Get the current selected suggestion and bail if it doesn't exist.
    let suggestionIndex = autocompleteState.selectedIndex;
    if (index || index === 0) suggestionIndex = index;
    const suggestion = suggestions[suggestionIndex];
    if (!suggestion) return;

    const config = autocompleteConfig.filter(x => x.trigger === autocompleteState.trigger)[0];

    this.updateEditorState(
      addAutocomplete(editorState, autocompleteState, suggestion, config),
    );

    // Sometimes the popup triggers again after setting an entity
    // We reset it to make sure this will never happen.
    this.setState({ autocompleteState: null });
  }

  /**
   * Probably need a better way of handling this but it works for now. We render the suggestion
   * template based on the config passed. Probably better to use a React component but will need to
   * rethink how that will work.
   */
  renderTemplate(item) {
    const { autocompleteConfig } = this.props;
    const { trigger } = this.state.autocompleteState;
    const config = autocompleteConfig.filter(x => x.trigger === trigger)[0];
    if (!config.suggestionTemplate) return item;
    return parseTemplate(config.suggestionTemplate, item);
  }

  /**
   * Handle Image upload on press.
   */
  uploadImages() {
    const { handleImageUpload, editorState } = this.props;
    const files = this.imageUpload.files;

    if (files.length === 0) return;

    Array.from(files).forEach((file) => {
      this.updateEditorState(
        addPlaceholder(editorState),
      );

      handleImageUpload(file).then((url) => {
        this.updateEditorState(
          addImage(editorState, false, url),
        );
      });
    });

    // Clear input value so the same file can be upload again if user wants to.
    this.imageUpload.value = '';
  }

  /**
   * Handle Image upload on drop. We bail for any other files.
   */
  handleDroppedFiles(selection, files) {
    const { handleImageUpload, editorState } = this.props;

    Array.from(files).forEach((file) => {
      if (!file.name.match(/.(jpg|jpeg|png|gif)$/i)) return;

      this.updateEditorState(
        addPlaceholder(editorState, selection),
      );

      handleImageUpload(file).then((url) => {
        this.updateEditorState(
          addImage(editorState, selection, url),
        );
      });
    });
  }

  /**
   * Draft doesn't handle certain commands by default as it generally expects
   * you to implement your own. However you can enable some default behavior
   * by using RichUtils.handleKeyCommand()
   */
  handleKeyCommand(command) {
    const { editorState } = this.props;
    const newState = RichUtils.handleKeyCommand(editorState, command);
    if (newState) {
      this.updateEditorState(newState);
      return 'handled';
    }
    return 'not-handled';
  }

  /**
   * If its an internal image we handle its repositioning. Everything else we bail on
   * because how the editor should handle other things will need some thought.
   */
  handleDrop(selection, dataTransfer, location) {
    if (location === 'external') return 'not-handled';
    const type = dataTransfer.data.getData('type');
    if (!type || type !== 'image') return 'not-handled';

    const { editorState } = this.props;
    const blockKey = dataTransfer.data.getData('key');

    this.updateEditorState(
      moveBlock(editorState, blockKey, selection),
    );

    return 'handled';
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
        <input
          ref={ref => this.imageUpload = ref}
          style={{ display: 'none' }}
          type="file"
          id={this.inputId}
          onChange={this.uploadImages.bind(this)}
          accept="image/jpeg,image/gif,image/png,image/jpg"
          capture="camera"
        />
        <Toolbar
          imageInputId={this.inputId}
          editor={this.editor}
          editorState={editorState}
          onChange={onChange}
        />
        <div style={{ position: 'relative' }} >
          <Editor
            editorState={editorState}
            onChange={this.updateEditorState.bind(this)}
            blockRendererFn={block => blockRenderer(block, editorState)}
            ref={node => this.editor = node}
            onDownArrow={this.onDownArrow.bind(this)}
            onUpArrow={this.onUpArrow.bind(this)}
            onEscape={this.onEscape.bind(this)}
            handleReturn={this.handleReturn.bind(this)}
            handleDroppedFiles={this.handleDroppedFiles.bind(this)}
            handleDrop={this.handleDrop.bind(this)}
            handleKeyCommand={this.handleKeyCommand.bind(this)}
            spellCheck={true}
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
  handleImageUpload: PropTypes.func,
};

export default EditorWrapper;
