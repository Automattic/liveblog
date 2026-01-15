/**
 * Tests for key event detection and processing in EditorContainer.
 *
 * These tests cover the /key command handling logic:
 * - hasKeyCommand: Detects /key in content
 * - stripKeyCommand: Removes /key from content
 * - processKeyEventContent: Adds/removes /key based on checkbox state
 */

// Extract the regex patterns and logic for testing
// These match the patterns in EditorContainer.js

/**
 * Check if content contains the /key command in any form.
 */
const hasKeyCommand = (content) => {
  if (!content) return false;
  // Check for /key at start of content or after non-word character
  const hasPlainKey = /(^|[^\w])\/key([^\w]|$)/i.test(content);
  // Check for transformed span version
  const hasSpanKey = /<span[^>]*class="liveblog-command[^"]*type-key"[^>]*>/i.test(content);
  return hasPlainKey || hasSpanKey;
};

/**
 * Strip /key command from content for display in editor.
 */
const stripKeyCommand = (content) => {
  if (!content) return '';

  let processed = content;

  // Remove /key when it's inside a paragraph (with optional br): <p>/key</p> or <p>/key<br></p>
  processed = processed.replace(/<p[^>]*>\s*\/key\s*(<br\s*\/?>)?\s*<\/p>\s*/gi, '');

  // Remove transformed span version (entire span element with optional trailing whitespace/br)
  processed = processed.replace(/<span[^>]*class="liveblog-command[^"]*type-key"[^>]*>[^<]*<\/span>[\s\n]*/gi, '');

  // Remove /key at the start of content (with optional trailing whitespace/br)
  processed = processed.replace(/^\/key[\s\n]*(<br\s*\/?>[\s\n]*)*/i, '');

  // Remove /key after HTML tags (e.g., after <p> or >)
  processed = processed.replace(/(>)\s*\/key[\s\n]*(<br\s*\/?>[\s\n]*)*/gi, '$1');

  // Clean up empty paragraphs (including those with just <br>)
  processed = processed.replace(/<p[^>]*>\s*(<br\s*\/?>)?\s*<\/p>\s*/gi, '');

  // Clean up leading <br> tags
  processed = processed.replace(/^[\s\n]*(<br\s*\/?>[\s\n]*)+/gi, '');

  return processed.trim();
};

/**
 * Process content to add or remove /key command based on checkbox state.
 */
const processKeyEventContent = (content, isKeyEvent, wasOriginallyKeyEvent) => {
  const hasKey = hasKeyCommand(content);

  if (isKeyEvent) {
    // Checkbox is checked - ensure /key is in content
    if (hasKey) {
      // Already has /key (manually typed), keep as-is
      return content;
    }
    // Add /key at the beginning
    return '/key ' + content;
  }

  // Checkbox is unchecked
  // Only strip /key if the entry WAS originally a key event
  if (hasKey && wasOriginallyKeyEvent) {
    return stripKeyCommand(content);
  }

  // Return content as-is (preserves manually typed /key)
  return content;
};

describe('hasKeyCommand', () => {
  describe('plain /key detection', () => {
    it('should detect /key at the start of content', () => {
      expect(hasKeyCommand('/key some text')).toBe(true);
    });

    it('should detect /key after HTML tag', () => {
      expect(hasKeyCommand('<p>/key some text</p>')).toBe(true);
    });

    it('should detect /key with newline after', () => {
      expect(hasKeyCommand('/key\nsome text')).toBe(true);
    });

    it('should detect /key followed by space', () => {
      expect(hasKeyCommand('/key breaking news')).toBe(true);
    });

    it('should not detect /key as part of another word', () => {
      expect(hasKeyCommand('/keyboard')).toBe(false);
    });

    it('should not detect /key preceded by word character', () => {
      expect(hasKeyCommand('my/key')).toBe(false);
    });

    it('should detect /key at end of content', () => {
      expect(hasKeyCommand('some text /key')).toBe(true);
    });
  });

  describe('span version detection', () => {
    it('should detect transformed span with type-key class', () => {
      expect(hasKeyCommand('<span class="liveblog-command type-key">key</span> text')).toBe(true);
    });

    it('should detect span with type-key at end of class', () => {
      // The regex expects class="liveblog-command...type-key" pattern (type-key before closing quote)
      expect(hasKeyCommand('<span class="liveblog-command foo type-key">key</span>')).toBe(true);
    });

    it('should not detect span without type-key class', () => {
      expect(hasKeyCommand('<span class="liveblog-command type-other">other</span>')).toBe(false);
    });
  });

  describe('edge cases', () => {
    it('should return false for empty content', () => {
      expect(hasKeyCommand('')).toBe(false);
    });

    it('should return false for null content', () => {
      expect(hasKeyCommand(null)).toBe(false);
    });

    it('should return false for undefined content', () => {
      expect(hasKeyCommand(undefined)).toBe(false);
    });

    it('should return false for content without /key', () => {
      expect(hasKeyCommand('<p>Regular content here</p>')).toBe(false);
    });
  });
});

describe('stripKeyCommand', () => {
  describe('plain /key removal', () => {
    it('should remove /key at the start of content', () => {
      expect(stripKeyCommand('/key some text')).toBe('some text');
    });

    it('should remove /key from inside paragraph', () => {
      expect(stripKeyCommand('<p>/key</p><p>content</p>')).toBe('<p>content</p>');
    });

    it('should remove /key after HTML tag', () => {
      expect(stripKeyCommand('<p>/key some text</p>')).toBe('<p>some text</p>');
    });

    it('should remove /key with trailing whitespace', () => {
      expect(stripKeyCommand('/key   text')).toBe('text');
    });
  });

  describe('span version removal', () => {
    it('should remove transformed span', () => {
      const input = '<span class="liveblog-command type-key">key</span> some text';
      expect(stripKeyCommand(input)).toBe('some text');
    });

    it('should remove span with trailing whitespace', () => {
      const input = '<span class="liveblog-command type-key">key</span>   text';
      expect(stripKeyCommand(input)).toBe('text');
    });
  });

  describe('cleanup', () => {
    it('should remove empty paragraphs', () => {
      expect(stripKeyCommand('<p></p><p>content</p>')).toBe('<p>content</p>');
    });

    it('should remove paragraphs with only br', () => {
      expect(stripKeyCommand('<p><br></p><p>content</p>')).toBe('<p>content</p>');
    });

    it('should trim result', () => {
      // Note: /key preceded by spaces is not stripped (not at start or after >)
      // Only the final result is trimmed
      expect(stripKeyCommand('/key text  ')).toBe('text');
    });
  });

  describe('edge cases', () => {
    it('should return empty string for empty content', () => {
      expect(stripKeyCommand('')).toBe('');
    });

    it('should return empty string for null content', () => {
      expect(stripKeyCommand(null)).toBe('');
    });

    it('should preserve content without /key', () => {
      expect(stripKeyCommand('<p>Regular content</p>')).toBe('<p>Regular content</p>');
    });
  });
});

describe('processKeyEventContent', () => {
  describe('checkbox checked (isKeyEvent = true)', () => {
    it('should add /key when content does not have it', () => {
      const result = processKeyEventContent('<p>some text</p>', true, false);
      expect(result).toBe('/key <p>some text</p>');
    });

    it('should preserve existing /key when content already has it', () => {
      const result = processKeyEventContent('/key some text', true, false);
      expect(result).toBe('/key some text');
    });

    it('should preserve existing span when content has transformed version', () => {
      const input = '<span class="liveblog-command type-key">key</span> text';
      const result = processKeyEventContent(input, true, false);
      expect(result).toBe(input);
    });
  });

  describe('checkbox unchecked (isKeyEvent = false)', () => {
    describe('entry was originally a key event', () => {
      it('should strip /key when unchecking existing key event', () => {
        const result = processKeyEventContent('/key some text', false, true);
        expect(result).toBe('some text');
      });

      it('should strip span when unchecking existing key event', () => {
        const input = '<span class="liveblog-command type-key">key</span> text';
        const result = processKeyEventContent(input, false, true);
        expect(result).toBe('text');
      });
    });

    describe('entry was NOT originally a key event', () => {
      it('should preserve manually typed /key in new entry', () => {
        const result = processKeyEventContent('/key some text', false, false);
        expect(result).toBe('/key some text');
      });

      it('should preserve manually typed /key when editing non-key entry', () => {
        const result = processKeyEventContent('<p>/key some text</p>', false, false);
        expect(result).toBe('<p>/key some text</p>');
      });
    });

    describe('no /key in content', () => {
      it('should return content as-is when no /key present', () => {
        const result = processKeyEventContent('<p>regular text</p>', false, false);
        expect(result).toBe('<p>regular text</p>');
      });

      it('should return content as-is when was key event but no /key now', () => {
        const result = processKeyEventContent('<p>regular text</p>', false, true);
        expect(result).toBe('<p>regular text</p>');
      });
    });
  });
});
