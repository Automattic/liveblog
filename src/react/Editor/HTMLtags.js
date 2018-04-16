/**
 * Tags that aren't currently supported by the renderer,
 * but makes sense to insert into the code block ui for a better user experience.
 */
export const CODE_BLOCK_TAGS = [
  'audio',
  'video',
  'embed',
  'object',
  'canvas',
  'table',
  'form',
  'div',
  'section',
  'iframe',
  'details',
];

/**
 * Text tags should always be mapped to a mutable entity, meaning they won't
 * be overwritten by draft if added in raw text edit mode.
 */
export const TEXT_TAGS = [
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'figcaption',
  'abbr',
  'b',
  'bdi',
  'bdo',
  'code',
  'dfn',
  'i',
  'kbd',
  'mark',
  'q',
  'rt',
  's',
  'samp',
  'small',
  'big',
  'span',
  'strong',
  'sub',
  'sup',
  'time',
  'u',
  'var',
  'wbr',
  'del',
  'ins',
  'blink',
  'font',
  'em',
  'bold',
  'br',
  'cite',
];

/**
 * @IMPORTANT Any children tags of tags that should be rendered as a code block should be ignored.
 * Tags to ignore completely by the renderer.
 * These may either need custom support or doesn't make sense to use.
 */
export const IGNORED_TAGS = [
  'head',
  'script',
  'track',
  'nav',
  'source',
  'param',
  'noscript', // this would never render anyway
  'caption',
  'col',
  'colgroup',
  'tbody',
  'td',
  'tfoot',
  'th',
  'thead',
  'tr',
  'input',
  'datalist',
  'fieldset',
  'frameset',
  'frame',
  'label',
  'textarea',
  'label',
  'legend',
  'meter',
  'optgroup',
  'option',
  'output',
  'progress',
  'select',
  'diaglog',
  'menu',
  'menuitem',
  'summary',
];
