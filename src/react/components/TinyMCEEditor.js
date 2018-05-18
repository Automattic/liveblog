/* global wp,  tinymce, jQuery */
/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */
/* eslint no-param-reassign: ["error", { "props": false }] */

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
  $textField.val('');
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
    this.containerId = `live-editor-${Math.floor(Math.random() * 100000)}`;
    this.wrapper = `${this.containerId}-wrapper`;
    this.editorSettings = window.liveblog_settings.editorSettings;
    setTimeout(() => {
      this.editorSettings.tinymce.setup = (editor) => {
        const stateContent = this.props.editorContainer.getContent();
        editor.clearAuthors = this.props.clearAuthors;
        setTimeout(() => {
          if (stateContent && stateContent !== '' && stateContent !== '<p></p>') {
            editor.setContent(stateContent);
          }
        }, 250);
      };
      wp.editor.initialize(this.containerId, this.editorSettings);
    }, 1000);
  }

  render() {
    return (
      <div className="liveblog-editor-wrapper" id={this.wrapper}>
        <textarea className="liveblog-editor-textarea" id={this.containerId} />
      </div>
    );
  }
}

export default TinyMCEEditor;
