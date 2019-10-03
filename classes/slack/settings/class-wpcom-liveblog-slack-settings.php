<?php

class WPCOM_Liveblog_Slack_Settings {

	const SETTINGS_PAGE_TITLE      = 'Slack Settings';
	const SETTINGS_SECTION_TITLE   = 'Settings';
	const SETTINGS_PAGE_CAPABILITY = 'edit_published_posts';
	const OPTION_NAME              = 'slack_liveblog_settings';
	const NONCE_ACTION             = 'slack-export-user';

	const SETTINGS = [
		'signing_secret'        => [
			'label' => 'Signing Secret',
			'type'  => 'text',
		],
		'oauth_access_token'    => [
			'label' => 'OAuth Access Token',
			'type'  => 'text',
		],
		'enable_event_endpoint' => [
			'label'       => 'Enable Event Endpoint',
			'type'        => 'checkbox',
			'description' => 'Enable the event endpoint at %slack-event-endpoint%',
		],
		'enable_entry_updates'  => [
			'label'       => 'Enable Entry Updates',
			'type'        => 'checkbox',
			'description' => 'Enable the ability to update an entry from slack',
		],
	];

	/**
	 * Register Hooks
	 */
	public static function hooks() {
		// Register Setting and Add Multivariate Hook.
		add_action( 'admin_init', [ __CLASS__, 'register_setting' ] );

		// Export users
		add_action( 'admin_init', [ __CLASS__, 'export_users' ] );

		// Create Admin Menu Page.
		add_action( 'admin_menu', [ __CLASS__, 'settings_page' ], 15 );
	}

	/**
	 * Register plugin settings
	 */
	public static function register_setting() {
		register_setting( self::OPTION_NAME, self::OPTION_NAME );

		add_settings_section(
			self::OPTION_NAME,
			self::SETTINGS_SECTION_TITLE,
			'__return_empty_string',
			self::OPTION_NAME
		);

		foreach ( self::SETTINGS as $setting_id => $setting ) {
			$options = [
				'name'        => self::OPTION_NAME . "[$setting_id]",
				'id'          => $setting_id,
				'type'        => $setting['type'],
				'description' => $setting['description'] ?? '',
			];
			add_settings_field(
				$setting_id,
				$setting['label'],
				[ __CLASS__, 'field' ],
				self::OPTION_NAME,
				self::OPTION_NAME,
				$options
			);
		}

		add_settings_field(
			'export-users',
			'Export Users',
			[ __CLASS__, 'export_field' ],
			self::OPTION_NAME,
			self::OPTION_NAME
		);

	}

	/**
	 * Render setting field
	 *
	 * @param $args
	 */
	public static function field( $args ) {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = $settings[ $args['id'] ] ?? '';
		if ( 'checkbox' === $args['type'] ) {
			printf( '<input type="%s" id="%s" name="%s" value="on"  class="widefat" %s/>', esc_attr( $args['type'] ), esc_attr( $args['id'] ), esc_attr( $args['name'] ), esc_attr( checked( 'on', $value, false ) ) );
		} else {
			printf( '<input type="%s" id="%s" name="%s" value="%s"  class="widefat"/>', esc_attr( $args['type'] ), esc_attr( $args['id'] ), esc_attr( $args['name'] ), esc_attr( $value ) );
		}

		if ( ! empty( $args['description'] ) ) {
			echo wp_kses_post( str_replace( '%slack-event-endpoint%', sprintf( '<i>%s</i>', home_url( '/wp-json/liveblog/' . WPCOM_Liveblog_Webhook_API::EVENT_ENDPOINT ) ), $args['description'] ) );
		}
	}

	/**
	 * Export user field
	 */
	public function export_field() {
		$url = wp_nonce_url( admin_url( 'options-general.php?page=slack_liveblog_settings&export=true' ), self::NONCE_ACTION );
		printf( '<a href="%s" class="button" target="_blank">Export Users</a>', esc_url( $url ) );
	}

	/**
	 * Add setting page link to options menu
	 */
	public static function settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . WPCOM_Liveblog_CPT::$cpt_slug,
			self::SETTINGS_PAGE_TITLE,
			self::SETTINGS_PAGE_TITLE,
			self::SETTINGS_PAGE_CAPABILITY,
			self::OPTION_NAME,
			[ __CLASS__, 'page_render' ]
		);
	}

	/**
	 * Render setting page
	 */
	public static function page_render() {
		?>

		<div class="wrap">
			<h2><?php echo esc_html( self::SETTINGS_PAGE_TITLE ); ?></h2>
			<form action="options.php" method="POST">
				<?php
				settings_fields( self::OPTION_NAME );
				do_settings_sections( self::OPTION_NAME );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Export users
	 */
	public static function export_users() {
		if ( ! empty( $_GET['page'] ) && self::OPTION_NAME === $_GET['page'] && isset( $_GET['export'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_ACTION ) ) { //phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected
			$user_export = new WPCOM_Liveblog_Export_Authors();
			$user_export->download();
		}
	}

}
