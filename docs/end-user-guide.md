# End user guide

Documentation for editors and site administrators using the Liveblog plugin.

## Creating a Liveblog

The liveblog lives inside of a regular WordPress post. First create a new post, complete with title, category, and maybe a short introduction. Once the liveblog plugin is installed, you will see a Liveblog box on your "Edit Post" page. Simply click "Enable" to activate it, and publish the post.

If you can't find the box, be sure that it is toggled on under "Screen Options" in the top right corner of the post editing page.

## Posting to the Liveblog

To post to the liveblog, navigate to the live post and start typing. Click "Publish Update," and your readers will see the post appear at the top of their screen. That's all there is to it.

## Adding a Photo

To add a photo to your update, simply drag and drop it into the posting box from your desktop. It will upload the image and include a link. To see the image, click "Preview."

You can also add photos from the internet by pasting in the direct URL to the image.

## Embedding Media

To embed media, paste the URL into the posting box on its own line. WordPress's standard media embeds apply, so common providers (YouTube, Vimeo, Twitter, Instagram, Spotify, etc.) work without any extra configuration.

## Formatting a Post

The liveblog posting box takes standard HTML formatting. To format text, simply wrap it in HTML tags.

Examples:

* `<strong>bold</strong>`
* `<em>italic</em>`
* `<u>underline</u>`

Links pasted directly into the posting box are automatically hyperlinked.

## Editing Previous Posts

While a liveblog is enabled, you can edit previous entries by clicking the "Edit" button next to the entry.

## Archiving a Liveblog

Once the event has wrapped up, you can archive your liveblog. Visitors still see the entries, but the editing tools go away and the post stops polling for updates. You can archive and re-enable a liveblog from the Edit Post page.

When a liveblog is archived, editors see a notification that the liveblog must be enabled to accept new entries.

## Smart Updates

The liveblog uses smart updates to make following along easy without overwhelming the reader. If the reader's browser is at the top of the post, new entries appear automatically, briefly highlighted.

If the reader has scrolled down to catch up on previous updates, the liveblog waits before adding new entries. A notification bar appears at the top of the screen instead. Clicking it brings the reader back to the top with new entries loaded.

Post times are relative ("2 minutes ago") and update every minute.

## Manually embed a Liveblog

If you need to insert the liveblog into your theme manually, the plugin provides a function that outputs the liveblog HTML on a post where the liveblog is enabled:

```php
wpcom_liveblog_get_output( $post_id );
```
