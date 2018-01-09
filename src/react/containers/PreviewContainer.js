import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { getPreview } from '../services/api';
import Loader from '../components/Loader';

class PreviewContainer extends Component {
  constructor(props) {
    super(props);

    this.state = {
      loading: true,
      error: false,
      entryContent: false,
    };
  }

  componentDidMount() {
    const { config, getEntryContent } = this.props;

    getPreview(getEntryContent(), config)
      .timeout(10000)
      .map(res => res.response)
      .subscribe(res => this.setState({
        entryContent: res.html,
        loading: false,
      }));
  }

  render() {
    const { entryContent, loading } = this.state;

    if (loading) {
      return (
        <div className="liveblog-preview"><Loader /></div>
      );
    }

    if (!entryContent) return false;

    return (
      <div
        className="liveblog-preview"
        dangerouslySetInnerHTML={{ __html: entryContent }}
      />
    );
  }
}

PreviewContainer.propTypes = {
  getEntryContent: PropTypes.func,
  config: PropTypes.object,
};

export default PreviewContainer;
