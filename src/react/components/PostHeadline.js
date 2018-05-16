import React from 'react';

const PostHeadline = () => (
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
      />
    </div>
  </div>
);


export default PostHeadline;
