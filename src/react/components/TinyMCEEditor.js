/* global wp,  tinymce, jQuery, _ */
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
        height: 300,
      },
      quicktags: true,
      mediaButtons: true,
    };
    setTimeout(() => {
      setTimeout(() => {
        const currentEditor = _.findWhere(tinymce.editors, { id: this.containerId });
        currentEditor.on('change', () => {
          const content = currentEditor.getContent();
          if (content) {
            this.props.editorContainer.setState({ rawText: content }, () => {
              this.props.editorContainer.syncRawTextToEditorState();
            });
          }
        });
        const stateContent = this.props.editorContainer.getContent();
        if (stateContent) {
          tinymce.activeEditor.setContent(stateContent);
        }
      }, 2000);
      wp.editor.initialize(this.containerId, this.editorSettings);
    }, 2000);
  }

  render() {
    return <div id={this.containerId} />;
  }
}

TinyMCEEditor.propTypes = {
};

export default TinyMCEEditor;
