/* eslint-disable no-return-assign */
import ReactDOM from 'react-dom';
import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { scrollElementIfNotInView } from './utils';

class Suggestions extends Component {

  componentDidUpdate(prevProps) {
    const { autocompleteState, onSearch, suggestions } = this.props;

    if (!autocompleteState) return;

    if (this.list && this[`item${autocompleteState.selectedIndex}`]) {
      scrollElementIfNotInView(
        this[`item${autocompleteState.selectedIndex}`],
        this.list,
      );
    }

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
      suggestions
    } = this.props;

    return suggestions
      .map((item, i) =>
        <li
          ref={ref => this[`item${i}`] = ref}
          style={{ height: '50px' }}
          className={`liveblog-popover-item ${autocompleteState.selectedIndex === i ? 'liveblog-popover-item--focused' : ''}`}
          key={i}
          onMouseDown={() => turnIntoEntity(i)}
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
      <div className="liveblog-popover">
        <div className="liveblog-popover-meta">
          {suggestions.length} {name}{suggestions.length > 1 ? 's' : ''} matching {trigger}{searchText}
        </div>
        <ul ref={ref => this.list = ref} style={{ maxHeight: '250px', overflowY: 'scroll', marginBottom: 0 }}>
          {this.renderSuggestions()}
        </ul>
      </div>
    );
  }
}

Suggestions.propTypes = {

};

export default Suggestions;
