# Hooking into entries

Five filters let you alter entries at different stages of their lifecycle.

## Before inserting into the database

```php
add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'filter' ), 10 );

public static function filter( $entry ) {
	return $entry;
}
```

## Before inserting an updated entry into the database

```php
add_filter( 'liveblog_before_update_entry', array( __CLASS__, 'filter' ), 10 );

public static function filter( $entry ) {
	return $entry;
}
```

## Before showing a preview

This is where `:emoji:` is converted back to `<img>`:

```php
add_filter( 'liveblog_preview_update_entry', array( __CLASS__, 'filter' ), 10 );

public static function filter( $entry ) {
	return $entry;
}
```

## Before allowing an entry to be edited

This is where `<img>` is converted back to `:emoji:`:

```php
add_filter( 'liveblog_before_edit_entry', array( __CLASS__, 'filter' ), 10 );

public static function filter( $content ) {
	return $content;
}
```

## Before the entry JSON is sent to the front end

```php
add_filter( 'liveblog_entry_for_json', array( __CLASS__, 'filter' ), 10, 2 );

public static function filter( $entry, $object ) {
	return $entry;
}
```
