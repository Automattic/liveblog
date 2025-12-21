/**
 * External dependencies
 */
const path = require('path');
const webpack = require('webpack');

/**
 * WordPress dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

/**
 * Extend the @wordpress/scripts webpack config with custom settings
 */
module.exports = function (env, argv) {
	const config = defaultConfig;

	// Custom entry points
	config.entry = {
		app: path.join(__dirname, './src/react/index.js'),
		amp: path.join(__dirname, './src/react/amp.js'),
	};

	// Custom output settings
	config.output = {
		...config.output,
		path: path.join(__dirname, './assets'),
		filename: '[name].js',
		chunkFilename: '[name].bundle.js',
		// Custom chunkLoadingGlobal to avoid conflicts
		chunkLoadingGlobal: 'wpJsonpLiveBlog',
		// Use 'auto' to determine publicPath at runtime from document.currentScript
		// This is required for React.lazy() dynamic imports to work correctly
		publicPath: 'auto',
	};

	// Enable source maps for better debugging
	config.devtool = 'source-map';

	// Configure sass-loader to suppress @import deprecation warnings
	// We'll migrate to @use in a future PR when we can properly refactor all SCSS
	const configureSassLoader = (rule) => {
		if (Array.isArray(rule.use)) {
			rule.use.forEach((loader) => {
				if (loader && typeof loader === 'object' &&
				    (loader.loader?.includes('sass-loader') || loader.loader?.includes('sass'))) {
					if (!loader.options) loader.options = {};
					if (!loader.options.sassOptions) loader.options.sassOptions = {};
					loader.options.sassOptions.quietDeps = true;
					loader.options.sassOptions.silenceDeprecations = ['import', 'global-builtin', 'color-functions'];
				}
			});
		}
		if (rule.oneOf) {
			rule.oneOf.forEach(configureSassLoader);
		}
	};

	config.module.rules.forEach(configureSassLoader);

	// Add custom plugins
	config.plugins.push(
		// Global vars for checking dev environment
		new webpack.DefinePlugin({
			__DEV__: JSON.stringify(process.env.NODE_ENV !== 'production'),
			__PROD__: JSON.stringify(process.env.NODE_ENV === 'production'),
			__TEST__: JSON.stringify(process.env.NODE_ENV === 'test'),
		}),
		// Ignore moment locales to reduce bundle size
		new webpack.IgnorePlugin({
			resourceRegExp: /^\.\/locale$/,
			contextRegExp: /moment$/,
		})
	);

	return config;
};
