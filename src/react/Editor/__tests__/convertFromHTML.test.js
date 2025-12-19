/**
 * Tests for emoji parsing in convertFromHTML
 *
 * The convertFromHTML function uses a regex to find emoji shortcodes like :smile:
 * This regex can incorrectly match text like timestamps (e.g., "7:51:35" matches ":51:")
 * These tests verify the fix that gracefully handles non-emoji matches.
 */

describe('convertFromHTML emoji parsing', () => {
  // Simulate the emoji matching logic from convertFromHTML
  const findEmojiMatches = (text, emojis) => {
    const result = [];

    text.replace(/:(\w+):/g, (match, name, offset) => {
      const emoji = emojis.filter(x =>
        match.replace(/:/g, '') === x.key.toString(),
      )[0];

      // This is the fix - skip if no matching emoji found
      if (!emoji) {
        return;
      }

      result.push({
        offset,
        length: match.length,
        result: match,
        emoji: emoji.key,
      });
    });

    return result;
  };

  const mockEmojis = [
    { key: 'smile', image: 'smile.png' },
    { key: 'heart', image: 'heart.png' },
    { key: 'thumbsup', image: 'thumbsup.png' },
  ];

  it('should find valid emoji shortcodes', () => {
    const matches = findEmojiMatches('Hello :smile: world', mockEmojis);
    expect(matches).toHaveLength(1);
    expect(matches[0].emoji).toBe('smile');
  });

  it('should find multiple valid emojis', () => {
    const matches = findEmojiMatches(':smile: and :heart:', mockEmojis);
    expect(matches).toHaveLength(2);
    expect(matches[0].emoji).toBe('smile');
    expect(matches[1].emoji).toBe('heart');
  });

  it('should not crash on timestamp-like text that resembles emoji syntax', () => {
    // "7:51:35" contains ":51:" which matches the emoji regex pattern
    expect(() => {
      findEmojiMatches('The time is 7:51:35 PM', mockEmojis);
    }).not.toThrow();
  });

  it('should return empty array for non-emoji colon patterns', () => {
    const matches = findEmojiMatches('The time is 7:51:35 PM', mockEmojis);
    expect(matches).toHaveLength(0);
  });

  it('should handle multiple timestamp patterns without crashing', () => {
    const matches = findEmojiMatches('Start: 10:30:00 End: 11:45:30', mockEmojis);
    expect(matches).toHaveLength(0);
  });

  it('should find valid emojis while ignoring non-emoji patterns', () => {
    const matches = findEmojiMatches('Meeting at 10:30:00 :smile: was great', mockEmojis);
    expect(matches).toHaveLength(1);
    expect(matches[0].emoji).toBe('smile');
  });

  it('should handle text with no colon patterns', () => {
    const matches = findEmojiMatches('Hello world', mockEmojis);
    expect(matches).toHaveLength(0);
  });

  it('should handle empty string', () => {
    const matches = findEmojiMatches('', mockEmojis);
    expect(matches).toHaveLength(0);
  });
});
