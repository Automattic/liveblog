# Auto-archiving live blog posts

This feature was added at the request of the community. It solves the issue of editors forgetting to archive old liveblogs and leaving them live indefinitely.

## How it works

Auto-archive sets an expiry on the post meta for liveblog posts. The expiry is calculated from the date of the latest liveblog entry plus the configured number of days.

When the expiry date is reached, the liveblog post is auto-archived. You can re-enable an auto-archived post: clicking "Enable" in the Liveblog meta box extends the expiry by the configured number of days from the latest entry. Auto-archive will then re-archive the post at the new expiry date.

## Configuration

The number of days is configured via a filter. The default value is `null`, which disables the feature. To enable it, return a value:

```php
add_filter( 'liveblog_auto_archive_days', function( $auto_archive_days ) {
	return 50;
}, 10, 1 );
```
