# Customisation

Hooks and extension points for theme and plugin developers.

## Entry features

The entry system supports `#hashtags`, `/commands`, `@authors` and `:emoji:` with autocomplete. These extensions are filtered on save. For example, a hashtag `#football` is saved as `<span class="liveblog-hash term-football">football</span>` so it can be styled. The container of the entry also receives the same `term-football` class.

### Commands

The command system has one built-in command:

* `/key` marks an entry as a key event. It adds the meta key `liveblog_key_entry` to the entry. A key event can be styled using the `.type-key` class.

To display a key event box you can add the `[liveblog_key_events]` shortcode in your theme (e.g. in the sidebar), or use the Liveblog Key Events widget. Entries used with `/key` are inserted into both this box and the main feed. The key events box also acts as an anchor system for jumping to parts of the main feed. The shortcode is not required for `/key` to work.

Example: an author writes the following in the New Entry box:

```
New iPad announced, launching next week — more info to come. /key
```

You can add new commands easily with a filter. The simplest command adds a class to the entry, so you could implement `/highlight` which adds `type-highlight` to the entry container, letting you style the background colour:

```php
add_filter( 'liveblog_active_commands', 'add_highlight_command', 10 );

function add_highlight_command( $commands ) {
	$commands[] = 'highlight';
	return $commands;
}
```

A command can have a filter called before the entry is saved or an action called after:

```php
apply_filters( "liveblog_command_{$command}_before", $arg );
do_action( "liveblog_command_{$command}_after", $arg );
```

### Customising the Key Events shortcode

You can change the shortcode title from the default "Key Events":

```
[liveblog_key_events title="My New Title"]
```

To remove the title (useful when placing the shortcode in a widget):

```
[liveblog_key_events title="false"]
```

A key event entry can be altered in two ways: **Template** and **Format**.

#### Templates

A template controls how each entry is rendered. There are two built-in templates: `list` and `timeline`. You can add your own with a filter:

```php
add_filter( 'liveblog_key_templates', 'add_template' );

function add_template( $templates ) {
	$templates['custom'] = array( 'key-events.php', 'div', 'liveblog-key-custom-css-class' );
	return $templates;
}
```

* `key-events.php` points to the template file in the `liveblog/` folder of the active theme directory (so the lookup is `liveblog/key-events.php`).
* `div` is the element type that wraps all entries. Use `ul` if you want a list.
* `liveblog-key-custom-css-class` is a class added to the wrapper element to help with styling.

An example template file:

```php
<div class="<?php echo esc_attr( $css_classes ); ?>">
	<a href="#liveblog-entry-<?php echo esc_attr( $entry_id ); ?>">
		<?php echo WPCOM_Liveblog_Entry_Key_Events::get_formatted_content( $content, $post_id ); ?>
	</a>
</div>
```

#### Formats

A format controls how each entry's content is filtered. There are three built-in formats:

* **Full** — shows content without filtering.
* **First Sentence** — returns everything up to the first `.`, `?` or `!`.
* **First Linebreak** — returns everything up to the first linebreak (Shift + Enter) or `<br />`.

Combining the `timeline` template with `First Sentence` for the entry:

```
New iPad announced. With plan to ship next month, pre-orders starting 23rd September retailing at $499 for 16gb $599 32gb. /key
```

The main feed shows the full text. The key events section shows only:

```
New iPad announced
```

You can add your own format with a filter:

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

A complete example registering both a template and a format:

```php
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

The chosen template and format are selected per post on the Edit Post page.

## Hashtags

Hashtags are managed in the admin area under Posts → Hashtags. Note that the hashtag's slug is matched, not its name.

## Emojis

When `:emoji:` is inserted into an entry it is converted to:

```html
<img src="//s.w.org/images/core/emoji/72x72/1f44d.png" class="liveblog-emoji emoji-+1">
```

## Altering hashtags, commands, authors and emoji

You can change the symbol and class prefix for `#hashtags`, `/commands`, `@authors` and `:emoji:` via filters:

```php
add_filter( 'liveblog_{type}_prefixes', array( __CLASS__, 'filter' ) );
add_filter( 'liveblog_{type}_class', array( __CLASS__, 'filter' ) );
```

For example, to use `!` instead of `#` for hashtags so they become `!hashtag`:

```php
add_filter( 'liveblog_hashtags_prefixes', array( __CLASS__, 'filter' ) );

public static function filter( $prefixes ) {
	$prefixes = array( '!', '\x{21}' );
	return $prefixes;
}
```

To change the class prefix for hashtags from `term-` to `hashtag-`:

```php
add_filter( 'liveblog_hashtags_class', array( __CLASS__, 'filter' ) );

public static function filter( $class_prefix ) {
	$class_prefix = 'hashtag-';
	return $class_prefix;
}
```

## Restricting shortcodes in entries

You can exclude shortcodes from being used within the content of a live entry. By default the built-in `[liveblog_key_events]` shortcode is excluded. Add others from your theme's `functions.php`:

```php
function liveblog_entry_add_restricted_shortcodes() {
	add_filter( 'liveblog_entry_restrict_shortcodes', 'add_shortcode_restriction', 10, 1 );

	function add_shortcode_restriction( $restricted_shortcodes ) {
		$restricted_shortcodes['my-shortcode']       = 'This Text Will Be Inserted Instead Of The Shortcode!';
		$restricted_shortcodes['my-other-shortcode'] = '<h1>Here is a Markup Shortcode Replacement</h1>';
		return $restricted_shortcodes;
	}
}

add_action( 'init', 'liveblog_entry_add_restricted_shortcodes' );
```

The function takes an associative array where the key is the shortcode and the value is the replacement string.

Given the example above, an editor adding any of these forms:

```
[my-shortcode]
[my-shortcode][/my-shortcode]
[my-shortcode arg="20"][/my-shortcode]
```

would see `This Text Will Be Inserted Instead Of The Shortcode!` rendered on the live entry.

By default the built-in `[liveblog_key_events]` shortcode is replaced with `We Are Blogging Live! Check Out The Key Events in The Sidebar`.

To override this default:

```php
$restricted_shortcodes['liveblog_key_events'] = 'Here is my alternative output for the shortcode! <a href="/">Click Here to Find Out More!</a>';
```

## Overriding default templates

Templates used by the plugin live in the [`templates/` directory](https://github.com/Automattic/liveblog/tree/develop/templates).

You can override these files in an upgrade-safe way: copy the files you want to change into a directory named `liveblog/` within the root of your theme, keeping the same filename.

For example, to override a single entry template, copy [`templates/liveblog-single-entry.php`](https://github.com/Automattic/liveblog/blob/develop/templates/liveblog-single-entry.php) to `yourtheme/liveblog/liveblog-single-entry.php`. The copied file overrides the plugin's default.

### Custom location for Liveblog templates

If `liveblog/` in the root of your theme doesn't suit your needs, use the `liveblog_template_path` filter to pass an absolute path (without a trailing slash) for template lookup.
