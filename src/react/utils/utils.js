/* eslint-disable import/prefer-default-export */

export const entryListChanged = (previous, current) =>
  previous.length !== current.length || previous[0].updated < current[0].updated;
