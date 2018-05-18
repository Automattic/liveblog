import React from 'react';
import PropTypes from 'prop-types';

class PostHeadline extends React.Component {
  updateInputValue(evt) {
    this.props.onChange(evt.target.value);
  }
  render() {
    return (
      <div className="liveblog-post-headline">
        <div className="liveblog-headline">
          <label className="screen-reader-text" id="headline-prompt-text" htmlFor="headline">Enter update headline here</label>
          <input
            type="text"
            name="post_headline"
            size="80"
            id="post_headline"
            spellCheck="true"
            autoComplete="off"
            placeholder="Enter update headline..."
            onChange={evt => this.updateInputValue(evt)}
            value={this.props.headline}
          />
        </div>
      </div>
    );
  }
}

PostHeadline.propTypes = {
  onChange: PropTypes.func,
  headline: PropTypes.string,
};

export default PostHeadline;
