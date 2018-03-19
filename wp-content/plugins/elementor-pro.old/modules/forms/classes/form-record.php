<?php
namespace ElementorPro\Modules\Forms\Classes;

use ElementorPro\Classes\Utils;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Form_Record {
	protected $sent_data;
	protected $fields;
	protected $form_type;
	protected $form_settings;
	protected $meta = [];

	public function get_formatted_data( $with_meta = false ) {
		$formatted = [];
		$no_label = __( 'No Label', 'elementor-pro' );
		$fields = $this->fields;

		if ( $with_meta ) {
			$fields = array_merge( $fields, $this->meta );
		}

		foreach ( $fields as $key => $field ) {
			if ( empty( $field['title'] ) ) {
				$formatted[ $no_label . ' ' . $key ] = $field['value'];
			} else {
				$formatted[ $field['title'] ] = $field['value'];
			}
		}

		return $formatted;
	}

	/**
	 * @param Ajax_Handler $ajax_handler
	 *
	 * @return bool
	 */
	public function validate( $ajax_handler ) {
		foreach ( $this->fields as $id => $field ) {
			if ( ! empty( $field['required'] ) && empty( $field['value'] ) ) {
				$ajax_handler->add_error( $id, Ajax_Handler::get_default_message( Ajax_Handler::FIELD_REQUIRED, $this->form_settings ) );
			}
		}

		do_action( 'elementor_pro/forms/validation', $this, $ajax_handler );

		return empty( $ajax_handler->errors );
	}

	public function get( $property ) {
		if ( isset( $this->{$property} ) ) {
			return $this->{$property};
		}

		return null;
	}

	public function get_form_settings( $setting ) {
		if ( isset( $this->form_settings[ $setting ] ) ) {
			return $this->form_settings[ $setting ];
		}

		return null;
	}

	public function get_field( $args ) {
		return wp_list_filter( $this->fields, $args );
	}

	public function remove_field( $id ) {
		unset( $this->fields[ $id ] );
	}

	private function set_meta() {
		$form_metadata = $this->form_settings['form_metadata'];

		if ( empty( $form_metadata ) ) {
			return;
		}

		foreach ( $form_metadata as $metadata_type ) {
			switch ( $metadata_type ) {
				case 'date' :
					$this->meta['date'] = [
						'title' => __( 'Date', 'elementor-pro' ),
						'value' => date_i18n( get_option( 'date_format' ) ),
					];
					break;

				case 'time' :
					$this->meta['time'] = [
						'title' => __( 'Time', 'elementor-pro' ),
						'value' => date_i18n( get_option( 'time_format' ) ),
					];
					break;

				case 'page_url' :
					$this->meta['page_url'] = [
						'title' => __( 'Page URL', 'elementor-pro' ),
						'value' => $_POST['referrer'],
					];
					break;

				case 'user_agent' :
					$this->meta['user_agent'] = [
						'title' => __( 'User Agent', 'elementor-pro' ),
						'value' => $_SERVER['HTTP_USER_AGENT'],
					];
					break;

				case 'remote_ip' :
					$this->meta['remote_ip'] = [
						'title' => __( 'Remote IP', 'elementor-pro' ),
						'value' => Utils::get_client_ip(),
					];
					break;
				case 'credit' :
					$this->meta['credit'] = [
						'title' => __( 'Powered by', 'elementor-pro' ),
						'value' => 'https://elementor.com/',
					];
					break;
			}
		}
	}

	private function set_fields() {
		foreach ( $this->form_settings['form_fields'] as $form_field ) {
			$field = [
				'id' => $form_field['_id'],
				'type' => $form_field['field_type'],
				'title' => $form_field['field_label'],
				'value' => '',
				'raw_value' => '',
				'required' => ! empty( $form_field['required'] ),
			];

			if ( isset( $this->sent_data[ $form_field['_id'] ] ) ) {
				$field['raw_value'] = $this->sent_data[ $form_field['_id'] ];
				$field['value'] = $field['raw_value'];
			}

			if ( is_array( $field['value'] ) ) {
				$field['value'] = implode( ', ', $field['value'] );
			}

			$this->fields[ $form_field['_id'] ] = $field;
		}
	}

	public function replace_setting_shortcodes( $setting ) {
		// Shortcode can be `[field id="fds21fd"]` or `[field title="Email" id="fds21fd"]`, multiple shortcodes are allowed
		return preg_replace_callback( '/(\[field[^]]*id="(\w+)"[^]]*\])/', function( $matches ) {
			$value = '';

			if ( isset( $this->fields[ $matches[2] ] ) ) {
				$value = $this->fields[ $matches[2] ]['value'];
			}

			return $value;
		} , $setting );
	}

	public function __construct( $sent_data, $form ) {
		$this->form_type = $form['widgetType'];
		$this->form_settings = $form['settings'];
		$this->sent_data = stripslashes_deep( $sent_data );

		$this->set_fields();
		$this->set_meta();
	}
}
