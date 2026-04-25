# Extending the admin meta box

The Liveblog admin meta box exposes filters and actions so you can add your own fields and capture their input. As an example, this guide adds a section with a text input and a button to save it.

## Adding fields

Use the `liveblog_admin_add_settings` filter to inject extra fields:

```php
add_filter( 'liveblog_admin_add_settings', array( __CLASS__, 'add_admin_options' ), 10, 2 );

public static function add_admin_options( $extra_fields, $post_id ) {
	$args = array(
		'new_label'  => __( 'My new field', 'liveblog' ),
		'new_button' => __( 'Save', 'liveblog' ),
	);

	$extra_fields[] = WPCOM_Liveblog::get_template_part( 'template.php', $args );
	return $extra_fields;
}
```

Template:

```php
<hr/>
<p>
	<label for="liveblog-new-input"><?php echo esc_html( $new_label ); ?></label>
	<input name="liveblog-new-input" type="text" value="" />
	<button type="button" class="button button-primary" value="liveblog-new-input-save"><?php echo esc_html( $new_button ); ?></button>
</p>
```

## Handling the save action

Catch the click on the save button by hooking into `liveblog_admin_settings_update`:

```php
add_action( 'liveblog_admin_settings_update', array( __CLASS__, 'save_template_option' ), 10, 3 );

public static function save_template_option( $response, $post_id ) {
	if ( 'liveblog-new-input-save' === $response['state'] && ! empty( $response['liveblog-new-input-save'] ) ) {
		// Handle your logic here.
	}
}
```
