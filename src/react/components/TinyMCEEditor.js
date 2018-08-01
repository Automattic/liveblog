/* global wp,  tinymce, jQuery */
/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import { debounce } from 'lodash-es';

export const maxContentLength = 65535;

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

export const setEnablePosting = () => {
  if (tinymce.activeEditor.setEnablePosting) {
    const content = tinymce.activeEditor.getContent();
    const postingEnabled = content.length > 0 && content.length <= maxContentLength;
    tinymce.activeEditor.setEnablePosting(postingEnabled);

    // show long text error message
    if (!postingEnabled && content.length > 0) {
      tinymce.activeEditor.setError(true, 'Text is too long!');
    } else {
      tinymce.activeEditor.setError(false, '');
    }
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
        tinymce.activeEditor.setEnablePosting = this.props.setEnablePosting;
        tinymce.activeEditor.setError = this.props.setError;
        tinymce.activeEditor.isError = false;
        if (stateContent && stateContent !== '' && stateContent !== '<p></p>') {
          tinymce.activeEditor.setContent(stateContent);
        }
        tinymce.activeEditor.off('keyup');
        tinymce.activeEditor.on('keyup', debounce(() => {
          setEnablePosting();
        }, 250));
        setEnablePosting();
        tinymce.activeEditor.focus(); // Set focus to active editor
      }, 250);
    }, 10);
  }

  componentDidUpdate() {
    const {
      error,
      errorMessage,
    } = this.props.errorData;
    /*
    could have used prevProps here to check error in previous props
    but somehow both previous and current props are same
    Using `isError` for current editor, we can prevent infinite loop
     */
    if (error && tinymce.activeEditor.setError && !tinymce.activeEditor.isError) {
      tinymce.activeEditor.setError(true, errorMessage);
      tinymce.activeEditor.isError = true;
      setTimeout(() => {
        tinymce.activeEditor.setError(false, errorMessage);
        tinymce.activeEditor.isError = false;
      }, 500);
    }
  }

  render() {
    return <textarea className="liveblog-editor-textarea" id={this.containerId} />;
  }
}

export default TinyMCEEditor;
