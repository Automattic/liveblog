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
    const content = getTinyMCEContent();
    const postingEnabled = content.length > 0 && content.length <= maxContentLength;
    tinymce.activeEditor.setEnablePosting(postingEnabled);

    // show long text error message
    if (!postingEnabled && content.length > 0) {
      tinymce.activeEditor.setError(true, 'This post is too long. Live blog posts are limited to 65535 characters.');
    } else {
      tinymce.activeEditor.setError(false, '');
    }
  }
};

/**
 * Define a function TinyMCE can call when an instance of an editor is initialized.
 *
 * @return {void}
 */
export const maybeCreateLiveblogInitInstanceCallback = () => {
  if (!window.liveblogInitInstance) {
    window.liveblogInitInstance = () => {
      jQuery(document).trigger('liveblogTinyMCEReady');
    };
  }
};

/**
 * Whether an editor be initialized using wp.editor.initialize.
 *
 * @return {Boolean} True if wp, wp.editor, and tinymce are present on the window.
 */
export const editorCanInitialize = () => {
  if (typeof wp === 'undefined' || typeof wp.editor === 'undefined' || typeof tinymce === 'undefined') {
    return false;
  }
  return true;
};

class TinyMCEEditor extends Component {
  constructor(props) {
    super(props);
    this.containerId = `live-editor-${Math.floor(Math.random() * 100000)}`;
    this.editorSettings = window.liveblog_settings.editorSettings;
    this.canInitialize = false;

    // Ensure tinymce initialization callback is defined.
    maybeCreateLiveblogInitInstanceCallback();

    // Bind editor setup callback to the tinymce initialization event.
    jQuery(document).on('liveblogTinyMCEReady', this.setupEditor.bind(this));
  }

  /**
   * Sets up the activeEditor to be used with this component instance.
   *
   * @memberof TinyMCEEditor
   */
  setupEditor() {
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
    // The change event handles the case of "Add Media" button press
    tinymce.activeEditor.on('keyup change paste', debounce(() => {
      setEnablePosting();
    }, 500));
    jQuery(document.getElementById(this.containerId)).on('keyup paste', debounce(() => {
      setEnablePosting(true);
    }, 500));
    setEnablePosting();
    tinymce.activeEditor.focus(); // Set focus to active editor
  }

  componentDidMount() {
    if (editorCanInitialize()) {
      // If the editor can be initialized, do so.
      wp.editor.initialize(this.containerId, this.editorSettings);
    } else {
      // If the editor can't be initialized yet, try again on document ready.
      jQuery(document).on('ready', () => {
        wp.editor.initialize(this.containerId, this.editorSettings);
      });
    }
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
    return <textarea className="liveblog-editor-textarea wp-editor-area" id={this.containerId} />;
  }
}

export default TinyMCEEditor;
