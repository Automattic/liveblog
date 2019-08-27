/* eslint-disable arrow-body-style */
import React, { useState } from 'react';
import PropTypes from 'prop-types';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import * as configActions from '../actions/configActions';
import * as apiActions from '../actions/apiActions';

const StatusFilter = ({ setStatus, getEntries }) => {
  const statuses = [
    { value: 'any', label: 'All Entries' },
    { value: 'draft', label: 'Draft Entries' },
    { value: 'publish', label: 'Publish Entries' },
  ];

  const [filter, setState] = useState('any');

  return (
    <div className="liveblog-status-filters">
      <strong>Filter Entries:</strong>

      { statuses.map((status) => {
        return filter !== status.value ? <a href="#" onClick={ (e) => {
          e.preventDefault();
          setStatus(status.value);
          setState(status.value);
          getEntries(1, window.location.hash);
        }}
        key={status.value}
        className="button-link">{status.label}</a> : <span>{status.label}</span>;
      }) }

    </div>
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
