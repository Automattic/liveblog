/* global wp, jQuery, tinymce */
/* eslint-disable no-return-assign */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';

class TinyMCEEditor extends Component {
  constructor(props) {
    super(props);
    this.containerId = `live-editor-${Math.floor(Math.random() * 10000)}`;
    this.editorSettings = {
      tinymce: {
        wpautop: true,
        plugins: 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
        toolbar1: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker',
      },
      quicktags: true,
    };
  }
  componentDidMount() {
    jQuery(document).on('ready', () => {
      this.editor = wp.editor.initialize(this.containerId, this.editorSettings);
      jQuery(document).on('tinymce-editor-init', () => {
        tinymce.editors.forEach((ed) => {
          if (this.containerId === ed.id) {
            ed.on('change blur', () => {
              const content = ed.getContent();
              if (content) {
                this.props.editorContainer.setState({ rawText: content }, () => {
                  this.props.editorContainer.syncRawTextToEditorState();
                });
              }
            });
          }
        });
      });
    });
  }

  render() {
    return <div id={this.containerId} />;
  }
}

TinyMCEEditor.propTypes = {
};

export default TinyMCEEditor;
