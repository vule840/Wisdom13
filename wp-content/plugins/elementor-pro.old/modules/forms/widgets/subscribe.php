<?php
namespace ElementorPro\Modules\Forms\Widgets;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Subscribe extends Form {

	public function get_name() {
		return 'subscribe';
	}

	public function get_title() {
		return __( 'Subscribe', 'elementor-pro' );
	}

	public function get_icon() {
		return 'eicon-mail';
	}

	protected function _register_controls() {
		parent::_register_controls();

		$this->update_control( 'form_name', [
			'default' => __( 'Subscribe Form', 'elementor-pro' ),
		] );

		$this->update_control( 'button_text', [
			'default' => __( 'Subscribe', 'elementor-pro' ),
		] );

		$this->update_control( 'form_fields', [
			'default' => [
				[
					'_id' => 'first_name',
					'field_label' => 'First Name',
					'field_type' => 'text',
				],
				[
					'_id' => 'last_name',
					'field_label' => 'Last Name',
					'field_type' => 'text',
				],
				[
					'_id' => 'email',
					'field_label' => 'Email',
					'field_type' => 'email',
					'required' => 1,
				],
			],
		] );

		$this->update_control( 'submit_actions', [
			'default' => [
				'mailchimp'
			],
		] );
	}
}
