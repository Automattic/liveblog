
# Liveblog

* Contributors: [automattic](http://profiles.wordpress.org/automattic), [nbachiyski](http://profiles.wordpress.org/nbachiyski), [batmoo](http://profiles.wordpress.org/batmoo), [johnjamesjacoby](http://profiles.wordpress.org/johnjamesjacoby), [philipjohn](http://profiles.wordpress.org/philipjohn)
* Tags: liveblog
* Requires at least: 3.5
* Tested up to: 4.2.2
* Stable tag: 1.4.1
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

[![Build Status](https://travis-ci.org/Automattic/liveblog.svg?branch=master)](https://travis-ci.org/Automattic/liveblog)

Quick and simple blogging for following fast-paced events.

## Description

[wpvideo tWpw6nCt]

Your readers want your updates as quickly as possible, and we think we provide the easiest and the most flexible publishing environment to make that happen. Sometimes though, that’s just not enough.

When you’re covering a fast-paced event — the latest Apple unveiling, an F1 Grand Prix, or the Super Bowl — a full blog post for each individual update is a poor experience for your authors and your audience.

The [WordPress.com VIP Liveblog Add-On](http://vip.wordpress.com/liveblog-add-on/) was purpose-built to address these issues specifically.

Here’s what makes it special:

 * Post updates right from the front-end of your site (no need to use the `/wp-admin` dashboard)
 * Viewers of your Liveblog get new entries served to them instantly and automatically, without needing to refresh their browser.
 * Your authors can drag-and-drop photos right into the Liveblog area, without needing to navigate to separate browser tabs or windows.
 * There’s no need for a separate site dedicated to liveblogging: *every* post can be a liveblog, even existing ones.

[Check out our in-depth documentation.](http://vip.wordpress.com/documentation/using-the-liveblog-plugin/)

If you'd like to check out the code and contribute, [join us on github](https://github.com/Automattic/liveblog), pull requests are more than welcome.

## Installation

1. Upload the `liveblog` folder to your plugins directory (e.g. `/wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You can enable the liveblog on any post's edit page

### Overview

The entry system supports `#hashtags`, `/commands`, `@authors` and `:emoji:` with an autocomplete system to help speed up the process. On top of this there is also a HTML5 notification section for users. These extensions are filtered on save, for example a hashtag `#football` would be saved as `<span class="liveblog-hash term-football">football</span>` allowing easy styling. The container of the entry will also receive the same class `term-football`.

The command system has one inbuilt command:

`/key`: Which defines an entry to a key event, it adds the meta key to entry `liveblog_key_entry`. A key event can be styled using the `.type-key` class.

To display a key event box you can add the `[liveblog_key_events]` shortcode in your theme, e.g. in the sidebar. Entries used with the key command will be inserted to both this box and the main feed. It also acts an anchor system to jump to parts of the main feed. It's not necessary to include the shortcode for the /key command to be enabled.

If the user has enabled HTML5 notifications and the window is not currently in focus, they will receive a notification about that entry.

An example of using the key command would be an author writing the following in the New Entry box:

```
New iPad announced, launching next week — more info to come. /key
```

You can add new commands easily with a filter, the most basic command will add a class to entry, so you could do a simple  `/highlight` which would add `type-highlight` to the entry container, letting you style the background color:

``` php
add_filter( 'liveblog_active_commands',  array( __CLASS__, 'add_highlight_command' ), 10 );


public static function add_highlight_command( $commands ) {
  $commands[] = highlight;
  return $commands;
}
```

A command can have both a filter called before the entry is saved, or an action that is called after it’s saved:

``` php
apply_filter( "liveblog_command_{$command}_before", $arg );
do_action( "liveblog_command_{$command}_after", $arg );
```

#### Customizing Key Events Shortcode

As mentioned earlier you can add the key events section by using `[liveblog_key_events]`. If you wish to change the title from the default `Key Events` then you can add a title attribute:

 ``` php
 [liveblog_key_events title="My New Title"]
 ```
 If want to remove the title, good example when placing the shortcode in a widget.

 ``` php
 [liveblog_key_events title="false"]
 ```

A key event entry can be altered in to two ways:

**Template:** This is how each entry will be rendered. There are two inbuilt templates: list and timeline, or you can add your own using a filter:

```php
add_filter( 'liveblog_key_templates', 'add_template' );

function add_template( $templates ) {
  $templates['custom'] = array( 'key-events.php', 'div', 'liveblog-key-custom-css-class' );
  return $templates;
}
```

There's a few things to note here:

* `key-events.php` points to `liveblog` folder in the current active theme directory, in this case we our loading template file `liveblog/key-events.php`.
* `div` is where we set the element type that wraps all entries, in the case where you wanted to build a list, you would set this to `ul`.
* `liveblog-key-custom-css-class` is a class that will be added to the wrapper element of the entry to help with styling, in this case it would look like: `<div class="liveblog-key-custom-css-class">...</div>`

An example of a template file is:

```php
<div class="<?php echo esc_attr( $css_classes ); ?>" >
	<a href="#liveblog-entry-<?php echo esc_attr( $entry_id ); ?>">
		<?php echo WPCOM_Liveblog_Entry_Key_Events::get_formatted_content( $content, $post_id ); ?>
	</a>
</div>
```

**Format:** This is how each entries content is filtered, there are three inbuilt formats:

* Full - which shows content without filtering
* First Sentence - which will return everything until it hits either `.?!`  
* First Linebreak - which will return everything until it hits a linebreak (Shift + Enter) or `<br />`.  

Formats add an extra level of control to how the key events section looks. If using the `timeline` template with `first sentence` and the following is entered into the new entry box:
```
New iPad announced. With plan to ship next month, pre-orders starting 23rd September retailing at $499 for 16gb $599 32gb. /key
```

The main feed and notification would show the full text, and the key events section would only show:
```
New iPad announced
```

You can add your own using a filter:

```php
add_filter( 'liveblog_key_formats', 'add_format' );

function add_format( $formats ) {
  $formats['strip-tags'] = 'new_format';
  return $formats;
}

function new_format( $content ) {
  $content = strip_tags( $content );
  return $content;
}
```

In the example above we are adding a format `Strip Tags`, which removes any HTML tags from the content.

Below is the full example of adding both:

``` php
function liveblog_add_key_event_template() {

	add_filter( 'liveblog_key_templates', 'add_template' );
	add_filter( 'liveblog_key_formats',   'add_format' );

	function add_template( $templates ) {
		$templates['custom'] = array( 'key-events.php', 'div', 'liveblog-key-custom-css-class' );
		return $templates;
	}

	function add_format( $formats ) {
		$formats['strip-tags'] = 'new_format';
		return $formats;
	}

	function new_format( $content ) {
		$content = strip_tags( $content );
		return $content;
	}
}

add_action( 'init', 'liveblog_add_key_event_template' );
```

Selecting which template or format to use for liveblog happens in the admin panel on the edit of page of the post:

![Key Events Admin Options](http://share.agnew.co/Gyai+)

#### Managing Hashtags
Hashtags are manageable in the admin area. Under Posts there will be a menu for Hashtags. Please note that the slug is used, not the name.

#### HTML5 Notifications
The notification checkbox for users will only appear if their browser supports the Notification API, the checkbox looks like:

![Notifications Checkbox](http://share.agnew.co/17Em3+)

#### Emjoi's
When a `:emoji:` is inserted into an entry it is converted into:

`<img src="//s.w.org/images/core/emoji/72x72/1f44d.png" class="liveblog-emoji emoji-+1">`

## Screenshots

![The entry form is the simplest possible](https://raw.github.com/Automattic/liveblog/master/screenshot-1.png)
![Writers can preview before posting](https://raw.github.com/Automattic/liveblog/master/screenshot-2.png)
![New posts are highlighted](https://raw.github.com/Automattic/liveblog/master/screenshot-4.png)
![Adding images is a matter of just drag-and-drop](https://raw.github.com/Automattic/liveblog/master/screenshot-5.png)
![Dragged photos are automatically inserted](https://raw.github.com/Automattic/liveblog/master/screenshot-6.png)
![Typical liveblog view](https://raw.github.com/Automattic/liveblog/master/screenshot-8.png)

## Changelog

### 1.4.1

* Bump tested tag to 4.2.2.
* Added Composer support!

### 1.4

* Rich-text editing!
* Archived liveblogs now display in chronological order (live ones show reverse chron)
* New and udpated translations
* Bump to fix SVN sync issues (thanks @kevinlisota)

### 1.3.1

* Fixed a bug where liveblog would show up in secondary loops

### 1.3

**The liveblog plugin now requires WordPress 3.5.**

New functionality:

* Liveblog archiving
* Shows automatically new entries, with a slick notification bar if we have scrolled out of view. With the help of [@borkweb](https://github.com/borkweb) and [@zbtirrell](https://github.com/zbtirrell)
* Front-end editing
* Pasting an image URL embeds the image

Translations:

* German by [@cfoellmann](https://github.com/cfoellmann)
* Spanish by [@elarequi](http://profiles.wordpress.org/elarequi)

Also a lot of internal improvements and bug fixes. See the [full list of
closed issues](https://github.com/Automattic/liveblog/issues?milestone=3&state=closed).

### 1.2

New functionality:

* Introduce many new hooks and filters, which help customization without changing the plugin code.
* Allow shortcodes and OEmbed in liveblog entries
* Translations:
	- Spanish by [@elarequi](http://profiles.wordpress.org/elarequi)
	- Dutch by [@defries](https://github.com/defries)
	- Catalan by [@gasparappa](https://github.com/gasparappa)
	- German by [@cfoellmann](https://github.com/cfoellmann)
* Add github-friendly version of `readme.txt`
* Optimize PNG files

Fixed problems:

* Fix JavaScript errors on IE8, props [@pippercameron](https://github.com/pippercameron)
* Fix preview tab
* Compatibility with plupload 1.5.4, props [@borkweb](https://github.com/borkweb)

### 1.1

* Backwards compatibility for 3.4
* Support for non-pretty permalinks
* Support for permalinks without trailing slashes
* Fix preview tab

### 1.0

* Initial release
