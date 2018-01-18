( function() {
    const {__} = wp.i18n;
    const {registerBlockType, InspectorControls} = wp.blocks;
    const { Button } = wp.components;

    registerBlockType('gutenberg/liveblog', {
        title: __('Liveblog'),
        icon: 'universal-access-alt',
        category: 'widgets',
        useOnce: true,
        customClassName: false,
        html: false,
        attributes: {
            status: {
                type: 'string',
                meta: 'liveblog',
                // source: 'meta',
                default: 0
            },
            showOptions: {
                type: 'string',
                default: 'show'
            }
        },
        edit({ attributes, className, setAttributes, focus }) {
            console.log(focus);
            const { status } = attributes;

            const showOptions = focus ? 'show' : 'hide';

            const doStatusChange = (status) => setAttributes({ status });
            let statusText;
            switch (status) {
                case 'enable':
                    statusText = 'enabled';
                    break;
                case 'archive':
                    statusText = 'archived';
                    break;
                default:
                    statusText = 'disabled';
            }

            const styles = {
                show: {
                    display: 'block',
                },
                hide: {
                    display: 'none',
                },
            };

            return (
                <section className={className}>
                    <h2>Liveblog</h2>
                    <p>Liveblog is currently <strong>{statusText}</strong>.<br /><small style={{ color: '#666', display: focus ? 'none' : 'block' }}><em>(Focus on this block to change this)</em></small></p>
                    <div style={styles[showOptions]}>
                        <div style={styles[status === 'enable' ? 'hide' : 'show']}>
                            <Button isPrimary={true} onClick={() => doStatusChange('enable')}>Enable Liveblog</Button>
                            <p>Enables liveblog on this post. Posting tools are enabled for editors, visitors get the latest updates.</p>
                        </div>
                        <div style={styles[status === 'archive' ? 'hide' : 'show']}>
                            <Button isLarge={true} onClick={() => doStatusChange('archive')}>Archive Liveblog</Button>
                            <p>Archives the liveblog on this post. Visitors still see the liveblog entries, but posting tools are hidden.</p>
                        </div>
                        <div style={styles[status === '0' ? 'hide' : 'show']}>
                            <Button isLarge={true} onClick={() => doStatusChange('0')}>Disable Liveblog</Button>
                            <p>Disables the liveblog on this post. Everything is hidden.</p>
                        </div>
                    </div>
                </section>
            );
        },
        save({ attributes: { status = 0 } = {} } = {}) {
            return `[liveblog status="${status}" /]`;
        },
    });
})();


