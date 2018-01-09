import { EditorState, RichUtils } from 'draft-js';

export default (editorState, url) => {
  const contentState = editorState.getCurrentContent();
  const contentStateWithEntity = contentState.createEntity(
    'LINK',
    'MUTABLE',
    { url },
  );

  const selection = editorState.getSelection();
  const newEditorState = EditorState.set(editorState, {
    currentContent: contentStateWithEntity,
  });

  return RichUtils.toggleLink(
    newEditorState,
    selection,
    contentStateWithEntity.getLastCreatedEntityKey(),
  );
};
