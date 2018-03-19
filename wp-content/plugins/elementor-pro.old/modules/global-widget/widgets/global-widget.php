<?php
namespace ElementorPro\Modules\GlobalWidget\Widgets;

use Elementor\Widget_Base;
use ElementorPro\Base\Base_Widget;
use ElementorPro\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Global_Widget extends Base_Widget {
	/**
	 * @var Widget_Base
	 */
	private $_original_element_instance;

	public function __construct( $data = [], $args = null ) {
		if ( $data ) {
			$template_data = Plugin::elementor()->templates_manager->get_template_data( [
				'source' => 'local',
				'template_id' => $data['templateID'],
			] );

			if ( is_wp_error( $template_data ) ) {
				throw new \Exception( $template_data->get_error_message() );
			}

			if ( empty( $template_data['content'] ) ) {
				throw new \Exception( 'Template content not found.' );
			}

			$data['settings'] = $template_data['content'][0]['settings'];
		}

		parent::__construct( $data, $args );
	}

	public function show_in_panel() {
		return false;
	}

	public function get_raw_data( $with_html_content = false ) {
		$raw_data = parent::get_raw_data( $with_html_content );

		unset( $raw_data['settings'] );

		$raw_data['templateID'] = $this->get_data( 'templateID' );

		return $raw_data;
	}

	public function render_content() {
		$this->get_original_element_instance()->render_content();
	}

	public function get_unique_selector() {
		return '.elementor-global-' . $this->get_data( 'templateID' );
	}

	public function get_name() {
		return 'global';
	}

	public function get_script_depends() {
		if ( $this->is_type_instance() ) {
			return [];
		}

		return $this->get_original_element_instance()->get_script_depends();
	}

	public function get_controls( $control_id = null ) {
		if ( $this->is_type_instance() ) {
			return [];
		}

		return $this->get_original_element_instance()->get_controls();
	}

	public function get_original_element_instance() {
		if ( ! $this->_original_element_instance ) {
			$this->_init_original_element_instance();
		}

		return $this->_original_element_instance;
	}

	public function on_export() {
		return $this->_get_template_content();
	}

	protected function _add_render_attributes() {
		parent::_add_render_attributes();

		$skin_type = $this->get_settings( '_skin' );

		$original_widget_type = $this->get_original_element_instance()->get_data( 'widgetType' );

		$this->add_render_attribute( '_wrapper', [
			'class' => [
				'elementor-global-' . $this->get_data( 'templateID' ),
				'elementor-widget-' . $original_widget_type,
			],
		] );

		$this->set_render_attribute( '_wrapper', 'data-element_type', $original_widget_type . '.' . ( $skin_type ? $skin_type : 'default' ) );
	}

	private function _get_template_content() {
		$template_data = Plugin::elementor()->templates_manager->get_template_data( [
			'source' => 'local',
			'template_id' => $this->get_data( 'templateID' ),
		] );

		return $template_data['content'][0];
	}

	private function _init_original_element_instance() {
		$template_content = $this->_get_template_content();

		$widget_type = Plugin::elementor()->widgets_manager->get_widget_types( $template_content['widgetType'] );

		$widget_class = $widget_type->get_class_name();

		$template_content['id'] = $this->get_id();

		$this->_original_element_instance = new $widget_class( $template_content, $widget_type->get_default_args() );
	}
}
