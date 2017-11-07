import ReactDOM from 'react-dom';
import React, { Component } from 'react';
import PropTypes from 'prop-types';

class SuggestionsContainer extends Component {

  componentDidUpdate(prevProps) {
    const { autocompleteState, onSearch } = this.props;
    if (!autocompleteState) return;

    if (!prevProps.autocompleteState && !autocompleteState) {
      return;
    }

    if (!prevProps.autocompleteState && autocompleteState) {
      onSearch(autocompleteState.trigger, autocompleteState.text);
      return;
    }

    if (prevProps.autocompleteState.text !== autocompleteState.text) {
      onSearch(autocompleteState.trigger, autocompleteState.text);
    }
  }

  renderSuggestions() {
    const { suggestions, autocompleteState, turnIntoEntity, selectedIndex } = this.props;
    const searchText = autocompleteState.text;

    return suggestions
      .filter(item => item.substring(0, searchText.length) === searchText)
      .map((item, i) =>
        <li
          className={`liveblog-popover-item ${selectedIndex === i ? 'liveblog-popover-item--focused' : ''}`}
          key={i}
          onMouseDown={() => turnIntoEntity(i)}
        >
          {item}
        </li>
      );
  }

  render() {
    const { autocompleteState, suggestions } = this.props;

    if (!autocompleteState) return false;

    return (
      <div className="liveblog-popover">
        <div className="liveblog-popover-meta">
          Showing {suggestions.length} suggestions for {autocompleteState.trigger}{autocompleteState.text}
        </div>
        <ul>
          {this.renderSuggestions()}
        </ul>
      </div>
    );
  }
}

SuggestionsContainer.propTypes = {

};

export default SuggestionsContainer;
