const path = require('path')

module.exports = {
  'parser': 'babel-eslint',
  'extends': [
    'airbnb-base',
    'plugin:react/recommended'
  ],
  'plugins': [
    'jest',
    'react'
  ],
  'globals': {
    '__DEV__': true,
    '__TEST__': true
  },
  'env': {
    'browser': true,
    'node': true,
    'jest/globals': true
  },
  'parserOptions': {
    'ecmaFeatures': {
      'jsx': true
    },
    'sourceType': 'module',
    'allowImportExportEverywhere': true
  },
  'settings': {
    'import/resolver': {
      [ path.resolve('./loaders/eslint-resolver.js') ]: { altSourceDir: process.env.LIVEBLOG_ALTERNATE_SOURCE_DIR, src: './src' }
    }
  }
}
