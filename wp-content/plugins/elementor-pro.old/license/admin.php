<?php
namespace ElementorPro\License;

use Elementor\Settings;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin {

	const PAGE_ID = 'elementor-license';

	public static $updater = null;

	private static function get_hidden_license_key() {
		$input_string = self::get_license_key();

		$start = 5;
		$length = mb_strlen( $input_string ) - $start - 5;

		$mask_string = preg_replace( '/\S/', 'X', $input_string );
		$mask_string = mb_substr( $mask_string, $start, $length );
		$input_string = substr_replace( $input_string, $mask_string, $start, $length );

		return $input_string;
	}

	public static function get_updater_instance() {
		if ( null === self::$updater ) {
			self::$updater = new Updater();
		}

		return self::$updater;
	}

	public static function get_license_key() {
		return trim( get_option( 'elementor_pro_license_key' ) );
	}

	public static function set_license_key( $license_key ) {
		return update_option( 'elementor_pro_license_key', $license_key );
	}

	public function action_activate_license() {
		check_admin_referer( 'elementor-pro-license' );

		if ( empty( $_POST['elementor_pro_license_key'] ) ) {
			wp_die( __( 'Please enter your license key.', 'elementor-pro' ), __( 'Elementor Pro', 'elementor-pro' ), [ 'back_link' => true ] );
		}

		$license_key = trim( $_POST['elementor_pro_license_key'] );

		$data = API::activate_license( $license_key );
		if ( is_wp_error( $data ) ) {
			wp_die( sprintf( '%s (%s) ', $data->get_error_message(), $data->get_error_code() ), __( 'Elementor Pro', 'elementor-pro' ), [ 'back_link' => true ] );
		}

		if ( API::STATUS_VALID !== $data['license'] ) {
			$errors = [
				'no_activations_left' => __( 'You have no more activations left. Please upgrade your licence for auto updates.', 'elementor-pro' ),
				'expired' => __( 'Your license has expired. Please renew your licence in order to get auto-updates.', 'elementor-pro' ),
				'missing' => __( 'Your license is missing. Please check your key again.', 'elementor-pro' ),
				'revoked' => __( 'Your license has been revoked.', 'elementor-pro' ),
				'item_name_mismatch' => sprintf( __( 'Your license has a name mismatch. Please go to <a href="%s" target="_blank">your purchases</a> and choose the proper key.', 'elementor-pro' ), 'https://go.elementor.com/my-account/' ),
			];

			if ( isset( $errors[ $data['error'] ] ) ) {
				$error_msg = $errors[ $data['error'] ];
			} else {
				$error_msg = __( 'An error occurred, please try again', 'elementor-pro' ) . ' (' . $data->error . ')';
			}

			wp_die( $error_msg, __( 'Elementor Pro', 'elementor-pro' ), [ 'back_link' => true ] );
		}

		self::set_license_key( $license_key );
		API::set_license_data( $data );

		wp_safe_redirect( $_POST['_wp_http_referer'] );
		die;
	}

	public function action_deactivate_license() {
		check_admin_referer( 'elementor-pro-license' );

		API::deactivate_license();

		delete_option( 'elementor_pro_license_key' );

		wp_safe_redirect( $_POST['_wp_http_referer'] );
		die;
	}

	public function register_page() {
		$menu_text = __( 'License', 'elementor-pro' );

		add_submenu_page(
			Settings::PAGE_ID,
			$menu_text,
			$menu_text,
			'manage_options',
			self::PAGE_ID,
			[ $this, 'display_page' ]
		);
	}

	public static function get_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_ID );
	}

	public function display_page() {
		$license_key = self::get_license_key();
		?>
		<div class="wrap">
			<h2><?php _e( 'License Setting', 'elementor-pro' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'elementor-pro-license' ); ?>

				<p><?php _e( 'Using this settings page you can set the license key that you purchased, then you can get automatic updates for Elementor Pro.', 'elementor-pro' ); ?></p>
	
				<h3><?php _e( 'Your License', 'elementor-pro' ); ?></h3>
	
				<p><?php printf( __( 'A license key qualifies you for support and enables automatic updates straight to your dashboard. Simply enter your license key where indicated and get started. If you don\'t have a license key, and haven\'t bought Elementor Pro yet, <a href="%s" target="_blank">buy it in our website</a>.', 'elementor-pro' ), 'https://go.elementor.com/pro-license/' ); ?></p>
				
				<?php if ( empty( $license_key ) ) : ?>
					<input type="hidden" name="action" value="elementor_pro_activate_license" />
	
					<label for="elementor-pro-license-key"><?php _e( 'License Key', 'elementor-pro' ); ?>:</label>
	
					<input id="elementor-pro-license-key" name="elementor_pro_license_key" type="text" value="" placeholder="<?php _e( 'Place Elementor Pro license key here', 'elementor-pro' ); ?>" class="regular-text" />
	
					<input type="submit" class="button button-primary" value="<?php _e( 'Activate', 'elementor-pro' ); ?>" />
	
					<p class="description"><?php printf( __( 'Please enter your license key. You can find your key in <a href="%s" target="_blank">your purchases</a>. License key looks similar to this: fb351f05958872E193feb37a505a84be', 'elementor-pro' ), 'https://go.elementor.com/my-account/' ); ?></p>

				<?php else :

					$license_data = API::get_license_data( true );
					?>
					<input type="hidden" name="action" value="elementor_pro_deactivate_license" />

					<label for="elementor-pro-license-key"><?php _e( 'License Key', 'elementor-pro' ); ?>:</label>

					<input id="elementor-pro-license-key" type="text" value="<?php echo esc_attr( self::get_hidden_license_key() ); ?>" class="regular-text" disabled />

					<input type="submit" class="button button-primary" value="<?php _e( 'Deactivate', 'elementor-pro' ); ?>" />

					<p>
						<?php _e( 'Status', 'elementor-pro' ); ?>:
						<?php if ( API::STATUS_EXPIRED === $license_data['license'] ) : ?>
							<span style="color: #ff0000; font-style: italic;"><?php _e( 'Expired', 'elementor-pro' ); ?></span>
						<?php elseif ( API::STATUS_SITE_INACTIVE === $license_data['license'] ) : ?>
							<span style="color: #ff0000; font-style: italic;"><?php _e( 'No Match', 'elementor-pro' ); ?></span>
						<?php else : ?>
							<span style="color: #008000; font-style: italic;"><?php _e( 'Active', 'elementor-pro' ); ?></span>
						<?php endif; ?>
					</p>

					<?php if ( API::STATUS_EXPIRED === $license_data['license'] ) : ?>
						<p><?php _e( '<strong>Your license has expired!</strong> Please <a href="https://my.elementor.com/purchases/" target="_blank">renew it or purchase a new one</a> in order to get updates.', 'elementor-pro' ); ?></p>
				<?php endif; ?>
					
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	public function admin_license_details() {
		$license_page_link = self::get_url();

		$license_key = self::get_license_key();
		if ( empty( $license_key ) ) {

			return;
		}

		$license_data = API::get_license_data();
		if ( empty( $license_data['license'] ) ) {
			return;
		}

		$errors = [
			API::STATUS_DISABLED => __( 'License is disabled', 'elementor-pro' ),
			API::STATUS_EXPIRED => __( 'License is expired', 'elementor-pro' ),
			API::STATUS_INVALID => sprintf( __( 'Something went wrong with license key is activated on your site. Please go to <a href="%s">your account in Elementor</a> to get an updated license key.', 'elementor-pro' ), 'https://go.elementor.com/my-account/' ),
			API::STATUS_SITE_INACTIVE => sprintf( __( 'The license does not match the domain registered in the system, please <a href="%s">deactivate the license</a> and try reinserting it to receive updates.', 'elementor-pro' ), $license_page_link ),
		];

		if ( isset( $errors[ $license_data['license'] ] ) ) {
			printf( '<div class="error"><p>%s</p></div>', $errors[ $license_data['license'] ] );
			return;
		}

		if ( API::STATUS_VALID === $license_data['license'] ) {
			$expires_time = strtotime( $license_data['expires'] );
			$notification_expires_time = strtotime( '-30 days', $expires_time );

			if ( $notification_expires_time <= current_time( 'timestamp' ) ) {
				$msg = sprintf( __( '<strong>Note:</strong> Your license key will expire in %s.', 'elementor-pro' ), human_time_diff( current_time( 'timestamp' ), $expires_time ) );
				printf( '<div class="update-nag">%s</div>', $msg );
			}
		}
	}

	public function filter_library_get_templates_args( $body_args ) {
		$license_key = self::get_license_key();

		if ( ! empty( $license_key ) ) {
			$body_args['license'] = $license_key;
		}

		return $body_args;
	}

	public function handle_tracker_actions() {
		// Show tracker notice after 24 hours from Pro installed time.
		$is_need_to_show = ( $this->_get_installed_time() < strtotime( '-24 hours' ) );

		$is_dismiss_notice = ( '1' === get_option( 'elementor_tracker_notice' ) );
		$is_dismiss_pro_notice = ( '1' === get_option( 'elementor_pro_tracker_notice' ) );

		if ( $is_need_to_show && $is_dismiss_notice && ! $is_dismiss_pro_notice ) {
			delete_option( 'elementor_tracker_notice' );
		}

		if ( ! isset( $_GET['elementor_tracker'] ) ) {
			return;
		}

		if ( 'opt_out' === $_GET['elementor_tracker'] ) {
			update_option( 'elementor_pro_tracker_notice', '1' );
		}
	}

	private function _get_installed_time() {
		$installed_time = get_option( '_elementor_pro_installed_time' );

		if ( ! $installed_time ) {
			$installed_time = time();
			update_option( '_elementor_pro_installed_time', $installed_time );
		}

		return $installed_time;
	}

	public function plugin_action_links( $links ) {
		$license_key = self::get_license_key();

		if ( empty( ! $license_key ) ) {
			$links['active_license'] = sprintf( '<a href="%s" class="elementor-plugins-gopro">%s</a>', self::get_url(), __( 'Activate License', 'elementor-pro' ) );
		}

		return $links;
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_page' ], 800 );
		add_action( 'admin_post_elementor_pro_activate_license', [ $this, 'action_activate_license' ] );
		add_action( 'admin_post_elementor_pro_deactivate_license', [ $this, 'action_deactivate_license' ] );

		add_action( 'admin_notices', [ $this, 'admin_license_details' ], 20 );

		// Add the licence key to Templates Library requests
		add_filter( 'elementor/api/get_templates/body_args', [ $this, 'filter_library_get_templates_args' ] );

		add_filter( 'plugin_action_links_' . ELEMENTOR_PRO_PLUGIN_BASE, [ $this, 'plugin_action_links' ], 50 );

		add_action( 'admin_init', [ $this, 'handle_tracker_actions' ], 9 );

		self::get_updater_instance();
	}
}
