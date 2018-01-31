(function() {
  tinymce.PluginManager.add('liveblog_button', function( editor, url ) {
    editor.addButton( 'liveblog_button', {
      text: 'Insert Liveblog',
      icon: false,
      onclick: function () {
        window.exposeEditor = editor;

        if (editor.contentDocument.documentElement.innerHTML.indexOf('[liveblog') !== -1 ) {
          return alert('Liveblog can only be present in a post once');
        }

        editor.insertContent('<!-- wp:gutenberg/liveblog {"status":"enable"} -->[liveblog status="enable" /]<!-- /wp:gutenberg/liveblog -->');
      }
    });
  });
})();
