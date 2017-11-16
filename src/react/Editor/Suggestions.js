/* eslint-disable no-return-assign */
import React, { Component } from 'react';
import PropTypes from 'prop-types';

const listStyle = {
  maxHeight: '150px',
  overflowY: 'scroll',
  marginBottom: 0,
};

const listItemStyle = {
  height: '30px',
};

class Suggestions extends Component {
  componentDidUpdate(prevProps) {
    const { autocompleteState, onSearch } = this.props;

    if (!autocompleteState) return;

    if (!prevProps.autocompleteState && autocompleteState) {
      onSearch(autocompleteState.trigger, autocompleteState.searchText);
      return;
    }

    if (prevProps.autocompleteState.searchText !== autocompleteState.searchText) {
      onSearch(autocompleteState.trigger, autocompleteState.searchText);
    }
  }

  renderSuggestions() {
    const {
      turnIntoEntity,
      autocompleteState,
      renderTemplate,
      suggestions,
      setSuggestionIndex,
    } = this.props;

    return suggestions
      .map((item, i) =>
        <li
          ref={ref => this[`item${i}`] = ref}
          style={listItemStyle}
          className={`liveblog-popover-item ${autocompleteState.selectedIndex === i ? 'is-focused' : ''}`}
          key={i}
          onMouseDown={() => turnIntoEntity(i)}
          onMouseEnter={() => setSuggestionIndex(i)}
          onTouchStart={() => setSuggestionIndex(i)}
          dangerouslySetInnerHTML={{ __html: renderTemplate(item) }}
        />,
      );
  }

  render() {
    const { autocompleteState, suggestions } = this.props;
    if (!autocompleteState) return false;

    const { trigger, searchText, name } = autocompleteState;

    if (suggestions.length === 0) return false;

    return (
      <div className="liveblog-popover" style={{ top: autocompleteState.top + 10 }}>
        <div className="liveblog-popover-meta">
          {suggestions.length} {name}{suggestions.length > 1 ? 's' : ''} matching {'"'}<b>{trigger}{searchText}</b>{'"'}
        </div>
        <ul ref={ref => this.list = ref} style={listStyle}>
          {this.renderSuggestions()}
        </ul>
      </div>
    );
  }
}

Suggestions.propTypes = {
  autocompleteState: PropTypes.object,
  onSearch: PropTypes.func,
  suggestions: PropTypes.array,
  turnIntoEntity: PropTypes.func,
  renderTemplate: PropTypes.func,
  setSuggestionIndex: PropTypes.func,
  getEditorRect: PropTypes.func,
};

export default Suggestions;
