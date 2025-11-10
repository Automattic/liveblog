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
	};

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
