/**
 * External dependencies
 */
const path = require('path');
const webpack = require('webpack');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');

/**
 * WordPress dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

/**
 * Extend the @wordpress/scripts webpack config with custom settings
 */
module.exports = function (env, argv) {
	const config = { ...defaultConfig };

	config.entry = {
		'frontend/app':  path.join( __dirname, './src/js/index.ts' ),
		'frontend/style': path.join( __dirname, './src/styles/theme.scss' ),
		'admin/admin':   path.join( __dirname, './src/admin/index.ts' ),
	};

	config.output = {
		...config.output,
		path: path.join(__dirname, './build'),
		filename: '[name].js',
		chunkFilename: '[name].bundle.js',
		chunkLoadingGlobal: 'wpJsonpLiveBlog',
		publicPath: 'auto',
	};

	config.devtool = argv.mode === 'development' ? 'source-map' : false;

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

	config.plugins = config.plugins.map((plugin) => {
		if (plugin instanceof DependencyExtractionWebpackPlugin) {
			return new DependencyExtractionWebpackPlugin({
				injectPolyfill: true,
				bundledPackages: [
					'@wordpress/dataviews',
					'@wordpress/ui',
					'@wordpress/private-apis',
					'@ariakit/react',
				],
			});
		}
		return plugin;
	});

	config.plugins.push(
		new webpack.DefinePlugin({
			__DEV__: JSON.stringify(process.env.NODE_ENV !== 'production'),
			__PROD__: JSON.stringify(process.env.NODE_ENV === 'production'),
			__TEST__: JSON.stringify(process.env.NODE_ENV === 'test'),
		}),
		new webpack.IgnorePlugin({
			resourceRegExp: /^\.\/locale$/,
			contextRegExp: /moment$/,
		})
	);

	return config;
};
