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
        plugins: 'charmap colorpicker fullscreen hr image  lists media paste tabfocus  wordpress wpautoresize wpdialogs wpeditimage wpgallery wplink wptextpattern wpview espnFootnote espnPromo espnPullquoteRight espnTwitter espnPullquote espnOrnamentalRule espnESPNVideo espnPodcasts espnDropcap',
        toolbar1: 'formatselect bold strikethrough bullist numlist blockquote alignleft aligncenter alignright link wp_more  wp_adv footnote ornamental-rule pullquote pullquote-right espn-video dropcap twitter promo podcasts | fullscreen',
        toolbar2: 'strikethru hr underline justifyfull forecolor | pastetext pasteword removeformat | media charmap | outdent indent | undo redo wp_help',

      },
      quicktags: true,
      mediaButtons: true,
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
