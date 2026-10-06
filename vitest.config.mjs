import { defineConfig, transformWithOxc } from 'vite';

export default defineConfig( {
	plugins: [
		{
			// Source keeps JSX in .js files (the wp-scripts convention), which
			// Vite only parses as plain JS. Transform it as JSX for tests.
			name: 'liveblog-jsx-in-js',
			enforce: 'pre',
			transform( code, id ) {
				if ( ! /\/src\/.*\.js$/.test( id ) ) {
					return null;
				}
				return transformWithOxc( code, id, {
					lang: 'jsx',
					jsx: { runtime: 'automatic' },
				} );
			},
		},
	],
	test: {
		environment: 'jsdom',
	},
} );
