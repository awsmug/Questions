<?php
/**
 * Element responses evaluator class
 *
 * @package TorroForms
 * @since 1.0.0
 */

namespace awsmug\Torro_Forms\Modules\Evaluators;

use awsmug\Torro_Forms\DB_Objects\Forms\Form;
use awsmug\Torro_Forms\DB_Objects\Submissions\Submission;
use awsmug\Torro_Forms\Modules\Assets_Submodule_Interface;
use awsmug\Torro_Forms\DB_Objects\Elements\Element_Types\Choice_Element_Type_Interface;
use WP_Error;

/**
 * Class for an evaluator for individual element responses.
 *
 * @since 1.0.0
 */
class Element_Responses extends Evaluator implements Assets_Submodule_Interface {

	/**
	 * Bootstraps the submodule by setting properties.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function bootstrap() {
		$this->slug        = 'element_responses';
		$this->title       = __( 'Element Responses', 'torro-forms' );
		$this->description = __( 'Evaluates individual element responses.', 'torro-forms' );
	}

	/**
	 * Evaluates a specific form submission.
	 *
	 * This method is run whenever a submission is completed to update the aggregate calculations.
	 * Aggregate calculations are stored so that forms with a very high number of submissions do
	 * not need to be calculated live.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array      $aggregate_results Aggregate results to update.
	 * @param Submission $submission        Submission to evaluate.
	 * @param Form       $form              Form the submission applies to.
	 * @return array Updated aggregate evaluation results.
	 */
	public function evaluate_single( $aggregate_results, $submission, $form ) {
		foreach ( $submission->get_submission_values() as $submission_value ) {
			if ( ! $this->is_element_evaluatable( $submission_value->element_id ) ) {
				continue;
			}

			if ( ! isset( $aggregate_results[ $submission_value->element_id ] ) ) {
				$aggregate_results[ $submission_value->element_id ] = array();
			}

			$field = ! empty( $submission_value->field ) ? $submission_value->field : '_main';
			if ( ! isset( $aggregate_results[ $submission_value->element_id ][ $field ] ) ) {
				$aggregate_results[ $submission_value->element_id ][ $field ] = array();
			}

			if ( ! isset( $aggregate_results[ $submission_value->element_id ][ $field ][ $submission_value->value ] ) ) {
				$aggregate_results[ $submission_value->element_id ][ $field ][ $submission_value->value ] = 1;
			} else {
				$aggregate_results[ $submission_value->element_id ][ $field ][ $submission_value->value ]++;
			}
		}

		return $aggregate_results;
	}

	/**
	 * Renders evaluation results for a specific form.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $results Results to show.
	 * @param Form  $form    Form the results belong to.
	 */
	public function show_results( $results, $form ) {
		$tabs = array();

		$elements = $this->module->manager()->forms()->get_child_manager( 'containers' )->get_child_manager( 'elements' );

		$element_ids = $form->get_elements( array(
			'fields' => 'ids',
		) );

		foreach ( $element_ids as $element_id ) {
			if ( ! $this->is_element_evaluatable( $element_id ) ) {
				continue;
			}

			$element = $elements->get( $element_id );
			if ( ! $element ) {
				continue;
			}

			// TODO: Multi-field element support.

			$tabs[ $element_id . '__main' ] = array(
				'label'    => $element->label,
				'callback' => function() use ( $results, $element, $form ) {
					$responses = array();
					$response_values = array();

					foreach ( $element->get_element_choices() as $element_choice ) {
						if ( ! empty( $element_choice->field ) ) {
							continue;
						}

						$responses[] = $element_choice->value;
						if ( isset( $results[ $element->id ]['_main'][ $element_choice->value ] ) ) {
							$response_values[] = (int) $results[ $element->id ]['_main'][ $element_choice->value ];
						} else {
							$response_values[] = 0;
						}
					}

					?>
					<p style="font-size:120%;text-align:center;">
						<strong>
							<?php echo esc_html( $element->label ); ?>
						</strong>
					</p>
					<div id="<?php echo esc_attr( $this->slug . '-chart-' . $element->id . '__main' ); ?>"></div>
					<script type="application/json" class="c3-chart-data">
						<?php echo json_encode( $this->get_chart_json( $form, esc_attr( $this->slug . '-chart-' . $element->id . '__main' ), $responses, $response_values ) ); ?>
					</script>
					<?php
				},
			);
		}

		if ( ! empty( $tabs ) ) {
			$this->display_tabs( $tabs );
		} else {
			echo '<p>' . __( 'This form does not contain any evaluatable elements.', 'torro-forms' ) . '</p>';
		}
	}

	/**
	 * Registers all assets the submodule provides.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param Assets $assets The plugin assets instance.
	 */
	public function register_assets( $assets ) {
		// Empty method body.
	}

	/**
	 * Enqueues scripts and stylesheets on the form editing screen.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param Assets $assets The plugin assets instance.
	 */
	public function enqueue_form_builder_assets( $assets ) {
		// Empty method body.
	}

	/**
	 * Enqueues scripts and stylesheets on the submissions list table view.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param Assets $assets The plugin assets instance.
	 * @param Form   $form   Form to show results for.
	 */
	public function enqueue_submission_results_assets( $assets, $form ) {
		$assets->enqueue_script( 'c3' );
		$assets->enqueue_style( 'c3' );
	}

	/**
	 * Checks whether the evaluator is enabled for a specific form.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param Form $form Form object to check.
	 * @return bool True if the evaluator is enabled, false otherwise.
	 */
	public function enabled( $form ) {
		return $this->get_form_option( $form->id, 'enabled', true );
	}

	/**
	 * Returns the available meta fields for the submodule.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Associative array of `$field_slug => $field_args` pairs.
	 */
	public function get_meta_fields() {
		$meta_fields = $this->_get_meta_fields();

		$meta_fields['enabled'] = array(
			'type'    => 'checkbox',
			'label'   => _x( 'Enable?', 'evaluator', 'torro-forms' ),
			'default' => true,
		);

		$meta_fields['display_mode'] = array(
			'type'    => 'select',
			'label'   => __( 'Display Mode', 'torro-forms' ),
			'choices' => array(
				'bar'   => __( 'Bar Chart', 'torro-forms' ),
				'pie'   => __( 'Pie Chart', 'torro-forms' ),
				'donut' => __( 'Donut Chart', 'torro-forms' ),
			),
			'default' => 'bar',
		);

		return $meta_fields;
	}

	/**
	 * Returns the JSON data to generate the chart.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @param Form   $form     Form object.
	 * @param string $id       ID attribute of the element to bind the chart to.
	 * @param array  $x_values Values for the 'x' axis.
	 * @param array  $y_values Values for the 'y' axis. Must match the number of $x_values passed.
	 * @return array JSON data for the chart.
	 */
	protected function get_chart_json( $form, $id, $x_values, $y_values ) {
		$display_mode = $this->get_form_option( $form->id, 'display_mode', 'bar' );

		if ( 'bar' === $display_mode ) {
			$less_than_10 = true;
			foreach ( $y_values as $y_value ) {
				if ( $y_value > 10 ) {
					$less_than_10 = false;
					break;
				}
			}

			array_unshift( $x_values, 'x' );
			array_unshift( $y_values, 'responseCount' );

			$data = array(
				'bindto' => '#' . $id,
				'data'   => array(
					'x'       => 'x',
					'columns' => array( $x_values, $y_values ),
					'names'   => array(
						'responseCount' => __( 'Response Count', 'torro-forms' ),
					),
					'type'    => $display_mode,
				),
				'axis'   => array(
					'x' => array(
						'type' => 'category',
					),
					'y' => array(
						'min'   => 1,
					),
				),
				'legend' => array(
					'show' => false,
				),
			);

			if ( $less_than_10 ) {
				$data['axis']['y']['max'] = 10;
			}

			return $data;
		}

		$data = array(
			'bindto' => '#' . $id,
			'data'   => array(
				'columns' => array(),
				'names'   => array(),
				'type'    => $display_mode,
			),
		);

		foreach ( $x_values as $index => $x_value ) {
			if ( ! isset( $y_values[ $index ] ) ) {
				continue;
			}

			$data['data']['columns'][] = array( 'data' . ( $index + 1 ), $y_values[ $index ] );
			$data['data']['names'][ 'data' . ( $index + 1 ) ] = $x_value;
		}

		return $data;
	}
}