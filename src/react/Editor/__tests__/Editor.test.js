/**
 * Basic smoke tests for the Editor component
 * These tests ensure the Editor can be imported and instantiated without errors
 */

import React from 'react';
import { EditorState } from 'draft-js';

describe('Editor', () => {
  let Editor;

  beforeAll(() => {
    // Mock draft-js CSS import
    jest.mock('draft-js/dist/Draft.css', () => ({}), { virtual: true });
  });

  it('should import without errors', () => {
    expect(() => {
      Editor = require('../Editor').default;
    }).not.toThrow();
  });

  it('should be a React component', () => {
    Editor = require('../Editor').default;
    expect(typeof Editor).toBe('function');
    expect(Editor.prototype.isReactComponent).toBeDefined();
  });

  it('should accept required props without errors', () => {
    Editor = require('../Editor').default;
    const editorState = EditorState.createEmpty();

    const mockProps = {
      editorState,
      onChange: jest.fn(),
      config: {
        autocomplete: {},
      },
      blocks: {},
      templates: {},
    };

    // Just verify the component can be constructed with valid props
    expect(() => {
      new Editor(mockProps);
    }).not.toThrow();
  });
});
