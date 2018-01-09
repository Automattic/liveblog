import { cancelPolling, startPolling } from '../actions/apiActions';
import { updateInterval } from '../actions/configActions';

export default ({ dispatch, getState }) => next => (action) => {
  if (action.type === 'POLLING_SUCCESS') {
    const { config } = getState();

    if (!Object.prototype.hasOwnProperty.call(action.payload, 'refresh_interval')) {
      return next(action);
    }

    const prev = parseInt(config.refresh_interval, 10);
    const latest = parseInt(action.payload.refresh_interval, 10);

    if (prev !== latest) {
      dispatch(cancelPolling());
      dispatch(updateInterval(latest));
      dispatch(startPolling());
    }
  }

  return next(action);
};
