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
  getTopPosition,
  focusableBlockIsSelected,
} from './utils';

import upArrowBinding from './keyBindings/upArrow';
import downArrowBinding from './keyBindings/downArrow';
import returnBinding from './keyBindings/returnBinding';
import keyBindingFunc from './keyBindings/keyBindingFunc';

import addAutocomplete from './modifiers/addAutocomplete';
import addAtomicBlock from './modifiers/addAtomicBlock';
import moveBlock from './modifiers/moveBlock';
import skipOverEntity from './modifiers/skipOverEntity';
import addNewLine from './modifiers/addNewLine';

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

  /**
   * If focus is set to a block on mount, set the selection to
   * just after it for a better UX. This can happen in an edge case
   * when there is a block at the top of the content when we are converting
   * from HTML to the editor.
   */
  componentDidMount() {
    const { onChange, editorState } = this.props;

    if (focusableBlockIsSelected(editorState)) {
      onChange(
        addNewLine(editorState),
      );
    }
  }

  /**
   * Hook into updating the editor state so we can check for any triggers
   * for the autocomplete every time the editor state is updated.
   */
  updateEditorState(editorState) {
    const { onChange, resetSuggestions, suggestions } = this.props;

    onChange(
      editorState,
    );

    // Wait until the state has been updated.
    setTimeout(() => {
      // Fix for selection getting 'stuck' in emoji as you have to pass through each character.
      const entity = hasEntityAtSelection(editorState);
      if (entity) {
        if (entity.getType() === ':') {
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
  * Down Arrow Binding
  * @param {event} event
  */
  onDownArrow(event) {
    const { autocompleteState } = this.state;
    const { editorState, onChange, suggestions } = this.props;
    return downArrowBinding(
      editorState,
      autocompleteState,
      onChange,
      this.setState.bind(this),
      event,
      this.suggestions,
      suggestions,
    );
  }

  /**
   * Up Arrow Binding
   * @param {event} event
   */
  onUpArrow(event) {
    const { autocompleteState } = this.state;
    const { editorState, onChange } = this.props;
    return upArrowBinding(
      editorState,
      autocompleteState,
      onChange,
      this.setState.bind(this),
      event,
      this.suggestions,
    );
  }

  /**
   * Escape arrow binding
   * @param {event} event
   */
  onEscape(event) {
    const { autocompleteState } = this.state;
    if (!autocompleteState) return 'not-handled';
    event.preventDefault();
    this.setState({ autocompleteState: null });
    return 'handled';
  }

  /**
   * Return arrow binding
   * @param {event} event
   */
  handleReturn(event) {
    const { autocompleteState } = this.state;
    const { suggestions, onChange, editorState } = this.props;

    return returnBinding(
      editorState,
      autocompleteState,
      onChange,
      event,
      suggestions,
      this.turnSuggestionIntoEntity.bind(this),
    );
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
      top: getTopPosition(range, this.editor.editorContainer),
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
    if (!config.template) return item;
    return parseTemplate(config.template, item, '');
  }

  /**
   * Handle Image upload on drop. We bail for any other files.
   */
  handleDroppedFiles(selection, files) {
    const { handleImageUpload, editorState, setReadOnly, defaultImageSize } = this.props;
    if (!files[0].name.match(/.(jpg|jpeg|png|gif)$/i)) return;

    this.updateEditorState(
      addAtomicBlock(editorState, false, {}, 'placeholder'),
    );

    handleImageUpload(files[0]).then((src) => {
      this.updateEditorState(
        addAtomicBlock(
          editorState,
          false,
          {
            setReadOnly,
            image: src,
            edit: false,
            handleImageUpload,
            defaultImageSize,
          },
          'media',
        ),
      );
    });
  }

  /**
   * Draft doesn't handle certain commands by default as it generally expects
   * you to implement your own. However you can enable some default behavior
   * by using RichUtils.handleKeyCommand()
   */
  handleKeyCommand(command) {
    if (command === 'handled') return 'handled';
    const { editorState } = this.props;
    const newState = RichUtils.handleKeyCommand(editorState, command);
    if (newState) {
      this.updateEditorState(newState);
      return 'handled';
    }
    return 'not-handled';
  }

  /**
  * If a draft block is dropped handle its reposition to the new selection. Annoyingly this
  * event never gets fired in IE11 and it silently fails. This issue has been documented
  * so hopefully will be resolved soon.
  * https://github.com/facebook/draft-js/issues/1174
  */
  handleDrop(selection, dataTransfer, location) {
    if (location === 'external') return 'handled';
    let handled = 'not-handled';
    const raw = dataTransfer.data.getData('text');
    const data = raw ? raw.split(':') : [];
    if (data.length !== 2) return 'not-handled';
    if (data[0] === 'DRAFT_BLOCK') {
      const { editorState } = this.props;
      const blockKey = data[1];
      this.updateEditorState(
        moveBlock(editorState, blockKey, selection),
      );
      handled = 'handled';
    }

    /**
     * Fix for an issue where drop breaks onChange. There is an open
     * pull request which fixes the issue which means we will no longer
     * need this once this has been merged.
     * https://github.com/facebook/draft-js/issues/1383
     */
    const mouseUpEvent = new MouseEvent('mouseup', {
      view: window,
      bubbles: true,
      cancelable: true,
    });
    this.editor.editor.dispatchEvent(mouseUpEvent);
    return handled;
  }

  render() {
    const { autocompleteState } = this.state;

    const {
      editorState,
      onChange,
      suggestions,
      onSearch,
      readOnly,
      setReadOnly,
      handleImageUpload,
      defaultImageSize,
    } = this.props;

    return (
      <div className="liveblog-editor-inner-container" onDrop={(event) => {
        // Fix for Draft Bug not always correctly handling handleDrop
        if (!event.target.isContentEditable) event.preventDefault();
      }}>
        <Toolbar
          editor={this.editor}
          editorState={editorState}
          onChange={onChange}
          handleImageUpload={handleImageUpload}
          setReadOnly={setReadOnly}
          readOnly={readOnly}
          defaultImageSize={defaultImageSize}
        />
        <div style={{ position: 'relative' }} >
          <Editor
            editorState={editorState}
            onChange={this.updateEditorState.bind(this)}
            blockRendererFn={block => blockRenderer(block, editorState, onChange)}
            ref={node => this.editor = node}
            onDownArrow={this.onDownArrow.bind(this)}
            onUpArrow={this.onUpArrow.bind(this)}
            onEscape={this.onEscape.bind(this)}
            handleReturn={this.handleReturn.bind(this)}
            handleDroppedFiles={this.handleDroppedFiles.bind(this)}
            handleDrop={this.handleDrop.bind(this)}
            handleKeyCommand={this.handleKeyCommand.bind(this)}
            keyBindingFn={event => keyBindingFunc(event, editorState, onChange)}
            spellCheck={true}
            readOnly={readOnly}
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
  readOnly: PropTypes.bool,
  setReadOnly: PropTypes.func,
  defaultImageSize: PropTypes.string,
};

export default EditorWrapper;
