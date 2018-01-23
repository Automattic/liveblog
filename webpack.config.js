const path = require('path');
const webpack = require('webpack');
const autoprefixer = require('autoprefixer');
const ExtractTextPlugin = require('extract-text-webpack-plugin');

const paths = {
  app: {
    name: 'app',
    entry: './src/react/index.js',
    out: './assets',
  },
  block: {
    name: 'dashboard/liveblog-block.build',
    entry: './src/gutenberg/liveblog-block.js',
    out: './assets/dashboard',
  },
};

const webpackConfig = {
  cache: true,
  context: path.resolve(__dirname, './src'),

  entry: {
    [paths.app.name]: path.join(__dirname, paths.app.entry),
    [paths.block.name]: path.join(__dirname, paths.block.entry),
  },

  output: {
    path: path.join(__dirname, paths.app.out),
    filename: '[name].js',
  },

  module: {
    rules: [
      // Run Babel and lint JS
      {
        test: /\.js$/,
        exclude: [/node_modules/],
        use: [
          {
            loader: 'babel-loader',
          },
          {
            loader: 'eslint-loader',
            options: {
              configFile: '.eslintrc',
              emitError: false,
              emitWarning: true,
            },
          },
        ],
      },
      {
        test: /\.scss$/,
        use: ExtractTextPlugin.extract({
          fallback: 'style-loader',
          use: [
            'css-loader',
            {
              loader: 'postcss-loader',
              options: {
                plugins: () => [
                  autoprefixer({
                    browsers: [
                      'last 3 version',
                      'ie >= 11',
                    ],
                  }),
                ],
              },
            },
            'sass-loader',
          ],
        }),
      },
      {
        test: /\.css$/,
        use: ExtractTextPlugin.extract({
          fallback: 'style-loader',
          use: [
            'css-loader',
          ],
        }),
      },
    ],
  },

  plugins: [
    new ExtractTextPlugin({ // define where to save the file
      filename: '[name].css',
      allChunks: true,
    }),
    // Global vars for checking dev environment.
    new webpack.DefinePlugin({
      __DEV__: JSON.stringify(process.env.NODE_ENV !== 'production'),
      __PROD__: JSON.stringify(process.env.NODE_ENV === 'production'),
      __TEST__: JSON.stringify(process.env.NODE_ENV === 'test'),
      'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV),
    }),
  ],
};

// Production/Dev Specific Config
if (process.env.NODE_ENV === 'production') {
  webpackConfig.plugins.push(new webpack.optimize.UglifyJsPlugin());
} else {
  webpackConfig.devtool = 'sourcemap';
}

module.exports = webpackConfig;
