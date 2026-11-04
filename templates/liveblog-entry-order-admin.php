<?php
/**
 * Template for the liveblog entry order admin setting.
 *
 * @package Liveblog
 */

?>
<label for="liveblog-entry-order"><?php esc_html_e( 'Entry order:', 'liveblog' ); ?></label>
<select id="liveblog-entry-order" name="liveblog-entry-order">
	<option value="desc" <?php selected( $entry_order, 'desc' ); ?>><?php esc_html_e( 'Newest first', 'liveblog' ); ?></option>
	<option value="asc" <?php selected( $entry_order, 'asc' ); ?>><?php esc_html_e( 'Oldest first', 'liveblog' ); ?></option>
</select>
<button type="button" class="button liveblog-entry-order-save" value="liveblog-entry-order-save"><?php esc_html_e( 'Save', 'liveblog' ); ?></button>
