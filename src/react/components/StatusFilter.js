/* eslint-disable arrow-body-style */
import React from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as configActions from '../actions/configActions';
import * as apiActions from '../actions/apiActions';

const StatusFilter = ({ setStatus, getEntries }) => {
  return (
    <React.Fragment>
      <label htmlFor="status-filter">Filter Entries</label>
      <select name="status-filter" id="status-filter" onChange={ (e) => {
        setStatus(e.target.value);
        getEntries(1, window.location.hash);
      }}>
        <option value="any">All Entries</option>
        <option value="draft">Draft Entries</option>
        <option value="publish">Publish Entries</option>
      </select>
    </React.Fragment>
  );
};

StatusFilter.propTypes = {
  setStatus: PropTypes.func,
  getEntries: PropTypes.func,
};

const mapStateToProps = state => state;

const mapDispatchToProps = dispatch =>
  bindActionCreators({
    ...apiActions,
    ...configActions,
  }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(StatusFilter);
