const path = require('path');
const webpack = require('webpack');
const autoprefixer = require('autoprefixer');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const ESLintPlugin = require('eslint-webpack-plugin');

const paths = {
  entry: './src/react/index.js',
  out: './assets',
};

const webpackConfig = {
  cache: true,
  context: path.resolve(__dirname, './src'),

  entry: {
    app: path.join(__dirname, paths.entry),
    amp: path.join(__dirname, './src/react/amp.js'),
  },

  output: {
    path: path.join(__dirname, paths.out),
    filename: '[name].js',
    chunkFilename: '[name].bundle.js',
    chunkLoadingGlobal: 'wpJsonpLiveBlog',
  },

  module: {
    rules: [
      // Run Babel
      {
        test: /\.js$/,
        exclude: [/node_modules/],
        use: [
          {
            loader: 'babel-loader',
          },
        ],
      },
      {
        test: /\.scss$/,
        use: [
			MiniCssExtractPlugin.loader,
			{
              loader: 'css-loader',
              options: {
                sourceMap: false,
              },
            },
            {
              loader: 'postcss-loader',
              options: {
				postcssOptions: {
					plugins: () => [
						autoprefixer({
							browsers: [
							'last 1 version',
							'ie >= 11',
							],
						}),
					],
				},
              },
            },
            {
              loader: 'sass-loader',
              options: {
                sourceMap: false,
              },
            },
        ],
      },
      {
        test: /\.css$/,
        use: [
			MiniCssExtractPlugin.loader,
			'css-loader',
		],
      },
    ],
  },

  plugins: [
    new MiniCssExtractPlugin({ // define where to save the file
      filename: '[name].css',
    }),
	new ESLintPlugin({
		extensions: ['.js'],
	}),
    // Global vars for checking dev environment.
    new webpack.DefinePlugin({
      __DEV__: JSON.stringify(process.env.NODE_ENV !== 'production'),
      __PROD__: JSON.stringify(process.env.NODE_ENV === 'production'),
      __TEST__: JSON.stringify(process.env.NODE_ENV === 'test'),
      'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV),
    }),
    new webpack.IgnorePlugin({resourceRegExp: /^\.\/locale$/, contextRegExp: /moment$/}),
  ],
};

// Production/Dev Specific Config
if (process.env.NODE_ENV === 'production') {
	webpackConfig.optimization = {
		minimize: true,
	}
} else {
  webpackConfig.devtool = 'eval-source-map';
}

module.exports = webpackConfig;
