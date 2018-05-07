/* global wp,  tinymce, jQuery */
/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';

export const getTinyMCEContent = () => {
  const currentEditor = tinymce.activeEditor;
  const $textField = jQuery(`#${currentEditor.id}`);
  if ($textField.is(':visible')) {
    return $textField.val();
  }

  return currentEditor ? currentEditor.getContent() : '';
};

export const clearTinyMCEContent = () => {
  const currentEditor = tinymce.activeEditor;
  const $textField = jQuery(`#${currentEditor.id}`);
  $textField.empty();
  currentEditor.setContent('');
};

export const clearAuthors = () => {
  if (tinymce.activeEditor.clearAuthors) {
    tinymce.activeEditor.clearAuthors();
  }
};


class TinyMCEEditor extends Component {
  constructor(props) {
    super(props);
    const {editorSettings} = props;
    this.containerId = `live-editor-${Math.floor(Math.random() * 100000)}`;
    this.editorSettings = editorSettings;
    setTimeout(() => {
      setTimeout(() => {
        const stateContent = this.props.editorContainer.getContent();
        tinymce.activeEditor.clearAuthors = this.props.clearAuthors;
        if (stateContent && stateContent !== '' && stateContent !== '<p></p>') {
          tinymce.activeEditor.setContent(stateContent);
        }
      }, 500);
      wp.editor.initialize(this.containerId, this.editorSettings);
    }, 1000);
  }

  render() {
    return <textarea className="liveblog-editor-textarea" id={this.containerId} />;
  }
}

export default TinyMCEEditor;
