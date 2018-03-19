<?php
namespace ElementorPro\Modules\Forms;

use ElementorPro\Base\Module_Base;
use ElementorPro\Modules\Forms\Actions\Activity_Log;
use ElementorPro\Modules\Forms\Actions\CF7DB;
use ElementorPro\Modules\Forms\Actions\Email;
use ElementorPro\Modules\Forms\Actions\Email2;
use ElementorPro\Modules\Forms\Actions\Mailchimp;
use ElementorPro\Modules\Forms\Actions\Mailpoet;
use ElementorPro\Modules\Forms\Actions\Redirect;
use ElementorPro\Modules\Forms\Actions\Webhook;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Honeypot_Handler;
use ElementorPro\Modules\Forms\Classes\Recaptcha_Handler;
use ElementorPro\Modules\Forms\Controls\Fields_Map;
use ElementorPro\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Module extends Module_Base {
	/**
	 * @var \ElementorPro\Modules\Forms\Classes\Action_Base[]
	 */
	private $form_actions = [];

	public function get_name() {
		return 'forms';
	}

	public function get_widgets() {
		return [
			'Form',
			//'Subscribe',
			'Login',
		];
	}

	public function localize_settings( $settings ) {
		$settings = array_replace_recursive( $settings, [
			'i18n' => [
				'x_field' => __( '{0} Field', 'elementor-pro' ),
			],
		] );
		return $settings;
	}

	public static function find_element_recursive( $elements, $form_id ) {
		foreach ( $elements as $element ) {
			if ( $form_id === $element['id'] ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) ) {
				$element = self::find_element_recursive( $element['elements'], $form_id );

				if ( $element ) {
					return $element;
				}
			}
		}

		return false;
	}

	public function register_controls() {
		$controls_manager = Plugin::elementor()->controls_manager;

		$controls_manager->register_control( Fields_Map::CONTROL_TYPE, new Fields_Map() );
	}

	public function forms_panel_action_data() {
		if ( empty( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'elementor-editing' ) ) {
			wp_send_json_error( new \WP_Error( 'token_expired' ) );
		}

		if ( empty( $_POST['service'] ) ) {
			wp_send_json_error( new \WP_Error( 'service_required' ) );
		}

		/** @var \ElementorPro\Modules\Forms\Classes\Action_Base $action */
		$action = $this->get_form_actions( $_POST['service'] );

		if ( ! $action ) {
			wp_send_json_error( new \WP_Error( 'action_not_found' ) );
		}

		try {
			$return_array = $action->handle_panel_request();

			wp_send_json_success( $return_array );

		} catch ( \Exception $exception ) {
			$return_array = [
				'message' => $exception->getMessage(),
			];

			wp_send_json_error( $return_array );
		}
	}

	public function add_form_action( $id, $instance ) {
		$this->form_actions[ $id ] = $instance;
	}

	public function get_form_actions( $id = null ) {
		if ( $id ) {
			if ( ! isset( $this->form_actions[ $id ] ) ) {
				return null;
			}

			return $this->form_actions[ $id ];
		}

		return $this->form_actions;
	}

	public function __construct() {
		parent::__construct();

		add_filter( 'elementor_pro/editor/localize_settings', [ $this, 'localize_settings' ] );
		add_action( 'elementor/controls/controls_registered', [ $this, 'register_controls' ] );
		add_action( 'wp_ajax_elementor_pro_forms_panel_action_data', [ $this, 'forms_panel_action_data' ] );

		$this->add_component( 'recaptcha', new Recaptcha_Handler() );
		$this->add_component( 'honeypot', new Honeypot_Handler() );

		// Actions Handlers
		$this->add_form_action( 'email', new Email() );
		$this->add_form_action( 'email2', new Email2() );
		$this->add_form_action( 'mailchimp', new Mailchimp() );
		$this->add_form_action( 'redirect', new Redirect() );
		$this->add_form_action( 'webhook', new Webhook() );

		// Plugins actions

		// MailPoet
		if ( class_exists( '\WYSIJA' ) ) {
			$this->add_form_action( 'mailpoet', new Mailpoet() );
		}

		// Add Actions as components, that runs manually in the Ajax_Handler

		// Activity Log
		if ( function_exists( 'aal_insert_log' ) ) {
			$this->add_component( 'activity_log', new Activity_Log() );
		}

		// Contact Form to Database
		if ( function_exists( 'CF7DBPlugin_init' ) ) {
			$this->add_component( 'cf7db', new CF7DB() );
		}

		// Ajax Handler
		if ( Ajax_Handler::is_form_submitted() ) {
			$this->add_component( 'ajax_handler', new Ajax_Handler() );

			do_action( 'elementor_pro/forms/form_submitted', $this );
		}
	}
}
