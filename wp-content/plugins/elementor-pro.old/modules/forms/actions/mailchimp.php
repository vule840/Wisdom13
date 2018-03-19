<?php

namespace ElementorPro\Modules\Forms\Actions;

use Elementor\Controls_Manager;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Controls\Fields_Map;
use ElementorPro\Modules\Forms\Classes\Action_Base;
use ElementorPro\Modules\Forms\Classes\Mailchimp_Handler;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Mailchimp extends Action_Base {

	public function get_name() {
		return 'mailchimp';
	}

	public function get_label() {
		return __( 'MailChimp', 'elementor-pro' );
	}

	public function register_settings_section( $widget ) {
		$widget->start_controls_section(
			'section_mailchimp',
			[
				'label' => __( 'MailChimp', 'elementor-pro' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'mailchimp_api_key',
			[
				'label' => __( 'API Key', 'elementor-pro' ),
				'type' => Controls_Manager::TEXT,
			]
		);

		$widget->add_control(
			'mailchimp_list',
			[
				'label' => __( 'List', 'elementor-pro' ),
				'type' => Controls_Manager::SELECT,
				'options' => [],
				'render_type' => 'none',
				'condition' => [
					'mailchimp_api_key!' => '',
				],
			]
		);

		$widget->add_control(
			'mailchimp_groups',
			[
				'label' => __( 'Groups', 'elementor-pro' ),
				'type' => Controls_Manager::SELECT2,
				'options' => [],
				'label_block' => true,
				'multiple' => true,
				'render_type' => 'none',
				'condition' => [
					'mailchimp_list!' => '',
				],
			]
		);

		$widget->add_control(
			'mailchimp_double_opt_in',
			[
				'label' => __( 'Double Opt-In', 'elementor-pro' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'condition' => [
					'mailchimp_list!' => '',
				],
			]
		);

		$widget->add_control(
			'mailchimp_fields_map',
			[
				'label' => __( 'Field Mapping', 'elementor-pro' ),
				'type' => Fields_Map::CONTROL_TYPE,
				'separator' => 'before',
				'fields' => [
					[
						'name' => 'remote_id',
						'type' => Controls_Manager::HIDDEN,
					],
					[
						'name' => 'local_id',
						'type' => Controls_Manager::SELECT,
					],
				],
				'condition' => [
					'mailchimp_list!' => '',
				],
			]
		);

		$widget->end_controls_section();
	}

	public function on_export( $element ) {
		unset(
			$element['settings']['mailchimp_api_key'],
			$element['settings']['mailchimp_list'],
			$element['settings']['mailchimp_groups'],
			$element['settings']['mailchimp_fields_map']
		);

		return $element;
	}

	public function run( $record, $ajax_handler ) {
		$subscriber = $this->map_fields( $record );
		$form_settings = $record->get( 'form_settings' );

		if ( ! empty( $form_settings['mailchimp_groups'] ) ) {
			$subscriber['interests'] = [];
		}

		foreach ( $form_settings['mailchimp_groups'] as $mailchimp_group ) {
			$subscriber['interests'][ $mailchimp_group ] = true;
		}

		$handler = new Mailchimp_Handler( $form_settings['mailchimp_api_key'] );

		$subscriber['status_if_new'] = 'yes' === $form_settings['mailchimp_double_opt_in'] ? 'pending' : 'subscribed';
		$subscriber['status'] = 'subscribed';

		$end_point = sprintf( 'lists/%s/members/%s', $form_settings['mailchimp_list'], md5( strtolower( $subscriber['email_address'] ) ) );

		$response = $handler->post( $end_point, $subscriber, [
			'method' => 'PUT', // Add or Update
		] );

		if ( 200 !== $response['code'] ) {
			$ajax_handler->add_error_message( Ajax_Handler::SERVER_ERROR );
		}
	}

	/**
	 * @param Form_Record $record
	 *
	 * @return array
	 */
	private function map_fields( $record ) {
		$subscriber = [];
		$fields = $record->get( 'fields' );

		// Other form has a field mapping
		foreach ( $record->get_form_settings( 'mailchimp_fields_map' ) as $map_item ) {
			if ( empty( $fields[ $map_item['local_id'] ]['value'] ) ) {
				continue;
			}

			$value = $fields[ $map_item['local_id'] ]['value'];
			if ( 'email' === $map_item['remote_id'] ) {
				$subscriber['email_address'] = $value;
			} else {
				$subscriber['merge_fields'][ $map_item['remote_id'] ] = $value;
			}
		}

		return $subscriber;
	}

	public function handle_panel_request() {
		if ( ! isset( $_POST['api_key'] ) ) {
			throw new \Exception( '`api_key` is required', 400 );
		}

		$handler = new Mailchimp_Handler( $_POST['api_key'] );
		if ( 'lists' === $_POST['mailchimp_action'] ) {
			return $handler->get_lists();
		}
		if ( 'list_details' === $_POST['mailchimp_action'] ) {
			return $handler->get_list_details( $_POST['mailchimp_list'] );
		}
	}
}
