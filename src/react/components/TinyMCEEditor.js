/* global wp,  tinymce, _, jQuery */
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
        plugins: 'charmap colorpicker hr lists paste textcolor fullscreen wordpress wpautoresize wpeditimage wpemoji wpgallery wplink wptextpattern hr image  lists media paste tabfocus  wordpress wpautoresize wpdialogs wpeditimage wpgallery wplink wptextpattern wpview espnFootnote espnESPNPromos espnPullquoteRight espnSocial espnPullquote espnOrnamentalRule espnESPNVideo espnPodcasts espnDropcap',
        toolbar1: 'formatselect bold strikethrough bullist numlist blockquote alignleft aligncenter alignright link wp_more  wp_adv footnote ornamental-rule pullquote pullquote-right espn-video dropcap social espn-promos podcasts | fullscreen',
        toolbar2: 'strikethru hr underline justifyfull forecolor | pastetext pasteword removeformat | media charmap | outdent indent | undo redo wp_help',
        height: 300,
      },
      quicktags: true,
      mediaButtons: true,
    };
    setTimeout(() => {
      setTimeout(() => {
        const currentEditor = _.findWhere(tinymce.editors, { id: this.containerId });
        const $textField = jQuery(`#${this.containerId}`);
        currentEditor.on('change', () => {
          const content = currentEditor.getContent();
          if (content) {
            this.props.editorContainer.setState({ rawText: content }, () => {
              this.props.editorContainer.syncRawTextToEditorState();
            });
          }
        });
        $textField.on('change blur', () => {
          const content = $textField.val();
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
      }, 500);
      wp.editor.initialize(this.containerId, this.editorSettings);
    }, 1000);
  }

  render() {
    return <textarea className="liveblog-editor-textarea" id={this.containerId} />;
  }
}

TinyMCEEditor.propTypes = {
};

export default TinyMCEEditor;
