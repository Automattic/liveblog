<?php

/**
 * Class WPCOM_Liveblog_Entry_Key_Events_Widget
 *
 * Class to create a widget that displays a list of key events.
 * This widget is just a wrapper for the shortcode
 * [liveblog_key_events].
 */
class WPCOM_Liveblog_Entry_Key_Events_Widget extends WP_Widget {

	/**
	 * Called by WPCOM_Liveblog::load(), it attaches the
	 * widget.
	 */
	public static function load() {
		add_action( 'widgets_init', array( __CLASS__, 'widgets_init' ) );
	}

	/**
	 * Registers the widget
	 */
	public static function widgets_init() {
		register_widget( __CLASS__ );
	}

	/**
	 * Configure widget
	 */
	public function __construct() {
		$widget_ops = array(
			'class_name'  => 'liveblog-key-events-widget',
			'description' => __( 'A list of key events displayed when the user is viewing a Liveblog post.', 'liveblog' ),
		);

		parent::__construct(
			'liveblog-key-events-widget',
			__( 'Liveblog Key Events Widget', 'liveblog' ),
			$widget_ops
		);
	}

	/**
	 * Output the list of key events for the current post.
	 *
	 * @param array $args
	 * @param array $instance
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$shortcode_output = WPCOM_Liveblog_Entry_Key_Events::shortcode( array( 'title' => false ) );

		if ( is_null( $shortcode_output ) ) {
			// Don't display the widget if there are no key events to show.
			return;
		}

		echo wp_kses_post( $args['before_widget'] );

		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}

		echo wp_kses_post( $shortcode_output );
		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Back-end form to display widget options (.
	 *
	 * @param array $instance Previously saved values from database.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'liveblog' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p class="description">
			<?php esc_html_e( 'Note: the number of key events displayed in the widget is defined in the Liveblog configuration inside each post.', 'liveblog' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';

		return $instance;
	}
}
