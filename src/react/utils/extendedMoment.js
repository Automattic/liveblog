/* eslint-disable no-confusing-arrow */
/* eslint-disable func-names */
import moment from 'moment';
/**
 * PHP => moment.js
 * Will take a php date format and convert it into a JS format for moment
 * Adapted from -
 * https://gist.github.com/phpmypython/f97c5f5f59f2a934599d
 */
const formatMap = {
  d: 'DD',
  D: 'ddd',
  j: 'D',
  l: 'dddd',
  N: 'E',
  S() { return `[${this.format('Do').replace(/\d*/g, '')}]`; },
  w: 'd',
  z() { return this.format('DDD') - 1; },
  W: 'W',
  F: 'MMMM',
  m: 'MM',
  M: 'MMM',
  n: 'M',
  t() { return this.daysInMonth(); },
  L() { return this.isLeapYear() ? 1 : 0; },
  o: 'GGGG',
  Y: 'YYYY',
  y: 'YY',
  a: 'a',
  A: 'A',
  B() {
    const thisUTC = this.clone().utc();
    const swatch = ((thisUTC.hours() + 1) % 24)
      + (thisUTC.minutes() / 60)
      + (thisUTC.seconds() / 3600);
    return Math.floor((swatch * 1000) / 24);
  },
  g: 'h',
  G: 'H',
  h: 'hh',
  H: 'HH',
  i: 'mm',
  s: 'ss',
  u: '[u]', // not sure if moment has this
  e: '[e]', // moment does not have this
  I() { return this.isDST() ? 1 : 0; },
  O: 'ZZ',
  P: 'Z',
  T: '[T]', // deprecated in moment
  Z() { return parseInt(this.format('ZZ'), 10) * 36; },
  c: 'YYYY-MM-DD[T]HH:mm:ssZ',
  r: 'ddd, DD MMM YYYY HH:mm:ss ZZ',
  U: 'X',
};

const formatEx = /[dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrU]/g;

moment.fn.formatUsingDateTime = function (format) {
  const that = this;
  return this.format(format.replace(formatEx, phpStr =>
    typeof formatMap[phpStr] === 'function'
      ? formatMap[phpStr].call(that)
      : formatMap[phpStr],
  ));
};

/**
 * Export moment to be used from this file, with extended features
 * as apose to importing from node_modules.
 */
export default moment;

