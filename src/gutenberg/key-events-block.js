/* eslint-disable wrap-iife */
/* eslint-disable func-names */
/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable react/prop-types */

(function () {
  const { __ } = window.wp.i18n;
  const { registerBlockType, Editable } = window.wp.blocks;

  registerBlockType('gutenberg/liveblog-key-events-block', {
    title: __('Liveblog Key Events'),
    icon: 'universal-access-alt',
    category: 'widgets',
    useOnce: true,
    customClassName: false,
    html: false,
    attributes: {
      title: {
        type: 'string',
        default: 'Key Events',
      },
    },
    edit({ className, setAttributes, attributes, focus }) {
      const { title } = attributes;
      const onChangeTitle = newTitle => setAttributes({ title: newTitle });

      return (
        <div className={className}>
          <h2>Liveblog Key Events</h2>
          <p>A list of key events displayed when the user is viewing a Liveblog post.</p>
          <div style={{ display: focus ? 'block' : 'none' }}>
            <h3 style={{ fontSize: '1.2rem', marginBottom: '.25rem' }}>
              Title:
            </h3>
            <Editable
              tagName="p"
              style={{
                display: 'block',
                padding: '.5rem',
                border: '1px solid #eee',
                flexGrow: '1',
              }}
              className={className}
              onChange={onChangeTitle}
              value={title}
              focus={false}
              onFocus={false}
            />
          </div>
        </div>
      );
    },
    save({ attributes: { title = 'Key Events' } }) {
      return `[liveblog_key_events title="${title}"]`;
    },
  });
})();
