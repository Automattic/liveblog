import 'core-js/es6/map';
import 'core-js/es6/set';
import 'core-js/es6/string';
import 'core-js/es6/array';
import 'core-js/es6/object';
import 'core-js/es6/promise';
import 'custom-event-polyfill';
import nodeListForEach from './nodeListForEach';

export default () => {
  nodeListForEach();
};
