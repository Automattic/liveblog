<?php
/**
 * Key Events Widget for liveblog entries.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress\Widget
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress\Widget;

use Automattic\Liveblog\Application\Service\KeyEventShortcodeHandler;
use Automattic\Liveblog\Infrastructure\DI\Container;
use WP_Widget;

/**
 * Widget that displays a list of key events.
 *
 * This widget is a wrapper for the liveblog_key_events shortcode,
 * allowing key events to be displayed in widget areas.
 *
 * Note: WordPress's widget system requires widgets to be instantiable
 * with no arguments. The shortcode handler is retrieved from the DI
 * container in the constructor.
 */
final class KeyEventsWidget extends WP_Widget {

	/**
	 * Shortcode handler for rendering key events.
	 *
	 * @var KeyEventShortcodeHandler|null
	 */
	private ?KeyEventShortcodeHandler $shortcode_handler = null;

	/**
	 * Constructor.
	 *
	 * WordPress instantiates widgets itself, so we retrieve the handler
	 * from the DI container rather than accepting it as a parameter.
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'   => 'liveblog-key-events-widget',
			'description' => __( 'A list of key events displayed when the user is viewing a Liveblog post.', 'liveblog' ),
		);

		parent::__construct(
			'liveblog-key-events-widget',
			__( 'Liveblog Key Events Widget', 'liveblog' ),
			$widget_ops
		);
	}

	/**
	 * Get the shortcode handler, lazy-loading from container.
	 *
	 * @return KeyEventShortcodeHandler|null
	 */
	private function get_shortcode_handler(): ?KeyEventShortcodeHandler {
		if ( null === $this->shortcode_handler ) {
			$this->shortcode_handler = Container::instance()->key_event_shortcode_handler();
		}
		return $this->shortcode_handler;
	}

	/**
	 * Output the list of key events for the current post.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance data.
	 */
	public function widget( $args, $instance ): void {
		$handler = $this->get_shortcode_handler();
		if ( null === $handler ) {
			return;
		}

		$shortcode_output = $handler->render( array( 'title' => false ) );

		if ( null === $shortcode_output ) {
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
	 * Back-end form to display widget options.
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ): void {
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
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ): array {
		return array(
			'title' => ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
		);
	}
}
