const path = require('path');
const webpack = require('webpack');
const autoprefixer = require('autoprefixer');
const ExtractTextPlugin = require('extract-text-webpack-plugin');

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
