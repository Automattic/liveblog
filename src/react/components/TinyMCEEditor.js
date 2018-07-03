/* global wp,  tinymce, jQuery */
/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import { debounce } from 'lodash-es';

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

export const clearHeadline = () => {
  if (tinymce.activeEditor.clearHeadline) {
    tinymce.activeEditor.clearHeadline();
  }
};

export const setPostingEnable = () => {
  if (tinymce.activeEditor.setPostingEnable) {
    const content = tinymce.activeEditor.getContent();
    const postingEnabled = content.length > 0 && content.length < 65535;
    tinymce.activeEditor.setPostingEnable(postingEnabled);
  }
};

class TinyMCEEditor extends Component {
  constructor(props) {
    super(props);
    this.containerId = `live-editor-${Math.floor(Math.random() * 100000)}`;
    this.editorSettings = window.liveblog_settings.editorSettings;
    setTimeout(() => { // wait for load
      wp.editor.initialize(this.containerId, this.editorSettings);
      setTimeout(() => {
        const stateContent = this.props.rawText;
        tinymce.activeEditor.clearAuthors = this.props.clearAuthors;
        tinymce.activeEditor.clearHeadline = this.props.clearHeadline;
        tinymce.activeEditor.setPostingEnable = this.props.setPostingEnable;
        if (stateContent && stateContent !== '' && stateContent !== '<p></p>') {
          tinymce.activeEditor.setContent(stateContent);
        }
        tinymce.activeEditor.off('keyup');
        tinymce.activeEditor.on('keyup', debounce(() => {
          setPostingEnable();
        }, 250));
        setPostingEnable();
        tinymce.activeEditor.focus(); // Set focus to active editor
      }, 250);
    }, 10);
  }

  render() {
    return <textarea className="liveblog-editor-textarea" id={this.containerId} />;
  }
}

export default TinyMCEEditor;
