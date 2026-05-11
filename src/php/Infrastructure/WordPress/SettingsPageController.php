<?php
/**
 * Settings page controller.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Service\SettingsService;

/**
 * Controller for the settings page.
 */
final class SettingsPageController {

	/**
	 * Settings service instance.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param SettingsService $settings_service Settings service.
	 */
	public function __construct( SettingsService $settings_service ) {
		$this->settings_service = $settings_service;
	}

	/**
	 * Initialize the settings page.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page to the admin menu.
	 */
	public function add_settings_page(): void {
		add_menu_page(
			__( 'Liveblog', 'liveblog' ),
			__( 'Liveblog', 'liveblog' ),
			'manage_options',
			'liveblog-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			'liveblog-settings',
			__( 'Liveblog Settings', 'liveblog' ),
			__( 'General', 'liveblog' ),
			'manage_options',
			'liveblog-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'liveblog',
			'liveblog_polling_interval',
			array(
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => array( $this->settings_service, 'sanitize_polling_interval' ),
			)
		);

		add_settings_section(
			'default',
			__( 'General Settings', 'liveblog' ),
			null,
			'liveblog-settings'
		);

		add_settings_field(
			'liveblog_polling_interval',
			__( 'Polling Interval (seconds)', 'liveblog' ),
			array( $this, 'render_polling_interval_field' ),
			'liveblog-settings',
			'default'
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'liveblog' );
				do_settings_sections( 'liveblog-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the polling interval field.
	 */
	public function render_polling_interval_field(): void {
		$value = $this->settings_service->get_polling_interval();
		?>
		<input type="number"
				name="liveblog_polling_interval"
				value="<?php echo esc_attr( $value ); ?>"
				min="1"
				max="60"
				class="small-text" />
		<p class="description">
			<?php esc_html_e( 'How often to check for new entries (1-60 seconds).', 'liveblog' ); ?>
		</p>
		<?php
	}
}