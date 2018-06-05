import types from './actionTypes';

export const getEvents = payload => ({
  type: types.GET_EVENTS,
  payload,
});

export const getEventsSuccess = payload => ({
  type: types.GET_EVENTS_SUCCESS,
  payload,
});

export const getEventsFailed = payload => ({
  type: types.GET_EVENTS_FAILED,
  payload,
});

export const deleteEvent = payload => ({
  type: types.DELETE_EVENT,
  payload,
});

export const deleteEventSuccess = payload => ({
  type: types.DELETE_EVENT_SUCCESS,
  payload,
});

export const deleteEventFailed = payload => ({
  type: types.DELETE_EVENT_FAILED,
  payload,
});

export const jumpToEvent = payload => ({
  type: types.JUMP_TO_EVENT,
  payload,
});
