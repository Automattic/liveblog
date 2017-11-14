import React from 'react';
import Loader from '../../components/Loader';

const Placeholder = () => (
  <div className="liveblog-placeholder">
    <div className="liveblog-placeholder-inner">
      <Loader />
      <div className="liveblog-placeholder-text">Uploading Image...</div>
    </div>
  </div>
);

export default Placeholder;
