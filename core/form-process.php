<?php
/**
 * Processing submitted form
 *
 * @author  awesome.ug, Author <support@awesome.ug>
 * @package AwesomeForms/Core
 * @version 1.0.0
 * @since   1.0.0
 * @license GPL 2
 *
 * Copyright 2015 awesome.ug (support@awesome.ug)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if( !defined( 'ABSPATH' ) )
{
	exit;
}

class AF_FormProcess
{

	/**
	 * ID of processed form
	 */
	var $form_id;

	/**
	 * Form object
	 */
	var $form;

	/**
	 * Action URL
	 */
	var $action_url;

	/**
	 * Initializes the Component.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $form_id, $action_url = NULL )
	{
		$this->form_id = $form_id;
		$this->form = new AF_Form( $this->form_id );

		if( NULL == $action_url )
		{
			$this->action_url = $_SERVER[ 'REQUEST_URI' ];
		}
		else
		{
			$this->action_url = $action_url;
		}
	}

	/**
	 * Show Form
	 *
	 * Creating form HTML
	 *
	 * @param int $form_id
	 *
	 * @return string $html
	 * @since 1.0.0
	 */
	public function show_form()
	{
		$show_form = apply_filters( 'af_form_show', TRUE ); // Hook for adding restrictions and so on ...

		if( FALSE == $show_form )
		{
			return;
		}

		if( !isset( $_SESSION ) )
		{
			session_start();
		}

		$html = '';

		// Set global message on top of page
		if( !empty( $af_response_errors ) )
		{
			$html .= '<div class="af-element-error">';
			$html .= '<div class="af-element-error-message"><p>';
			$html .= esc_attr__( 'There are open answers', 'af-locale' );
			$html .= '</p></div></div>';
		}

		// Getting actual step for form
		$actual_step = $this->get_actual_step();

		$html .= '<form class="af-form" action="' . $this->action_url . '" method="POST">';
		$html .= '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce( 'af-form-' . $this->form_id ) . '" />';

		$step_count = $this->form->get_step_count();

		// Switch on navigation if there is more than one page
		if( 0 != $step_count )
		{
			$html .= '<div class="af-pagination">' . sprintf( __( 'Step <span class="af-highlight-number">%d</span> of <span class="af-highlight-number">%s</span>', 'af-locale' ), $actual_step + 1, $step_count + 1 ) . '</div>';
		}

		// Getting all elements of step and running them
		$elements = $this->form->get_step_elements( $actual_step );
		$next_step = $actual_step;

		ob_start();
		do_action( 'af_form_start', $this->form_id, $actual_step, $step_count );
		$html .= ob_get_clean();

		if( is_array( $elements ) && count( $elements ) > 0 )
		{
			foreach( $elements AS $element )
			{
				if( !$element->splits_form )
				{
					$html .= $element->draw();
				}
				else
				{
					$next_step += 1; // If there is a next step, setting up next step var
					break;
				}
			}
		}
		else
		{
			return FALSE;
		}

		$html .= $this->get_navigation( $actual_step, $next_step );

		ob_start();
		do_action( 'af_form_end', $this->form_id, $actual_step, $step_count );
		$html .= ob_get_clean();

		$html .= '<input type="hidden" name="af_next_step" value="' . $next_step . '" />';
		$html .= '<input type="hidden" name="af_actual_step" value="' . $actual_step . '" />';
		$html .= '<input type="hidden" name="af_form_id" value="' . $this->form_id . '" />';

		$html .= '</form>';

		return $html;
	}

	/**
	 * Getting actual step by POST data and error response
	 *
	 * @return int
	 */
	public function get_actual_step()
	{
		global $af_response_errors;

		// If there was posted af_next_step and there was no error
		if( isset( $_POST[ 'af_next_step' ] ) && 0 == count( $af_response_errors ) )
		{
			$actual_step = (int) $_POST[ 'af_next_step' ];
		}
		else
		{

			// If there was posted af_next_step and there was an error
			if( isset( $_POST[ 'af_actual_step' ] ) )
			{
				$actual_step = (int) $_POST[ 'af_actual_step' ];
				// If there was nothing posted, start at the beginning
			}
			else
			{
				$actual_step = 0;
			}
		}

		// If user wanted to go backwards, set one step back
		if( array_key_exists( 'af_submission_back', $_POST ) )
		{
			$actual_step = (int) $_POST[ 'af_actual_step' ] - 1;
		}

		return $actual_step;
	}

	/**
	 * Getting navigation for form
	 *
	 * @param $actual_step
	 * @param $next_step
	 *
	 * @return string
	 */
	public function get_navigation( $actual_step, $next_step )
	{
		$html = '';

		// If there was a step before, show previous button
		if( $actual_step > 0 )
		{
			$html .= '<input type="submit" name="af_submission_back" value="' . __( 'Previous Step', 'af-locale' ) . '"> ';
		}

		if( $actual_step == $next_step )
		{
			// If actual step is next step, show finish form button
			ob_start();
			do_action( 'af_form_send_button_before', $this->form_id );
			$html .= ob_get_clean();

			$html .= '<input type="submit" name="af_submission" value="' . __( 'Send', 'af-locale' ) . '">';

			ob_start();
			do_action( 'af_form_send_button_after', $this->form_id );
			$html .= ob_get_clean();
		}
		else
		{
			// Show next button
			$html .= '<input type="submit" name="af_submission" value="' . __( 'Next Step', 'af-locale' ) . '">';
		}

		return $html;
	}

	/**
	 * Processing entered data
	 *
	 * @since 1.0.0
	 */
	public function process_response()
	{
		global $ar_form_id, $af_response_errors;

		if( !wp_verify_nonce( $_POST[ '_wpnonce' ], 'af-form-' . $ar_form_id ) )
		{
			return;
		}

		$response = array();
		if( isset( $_POST[ 'af_response' ] ) )
		{
			$response = $_POST[ 'af_response' ];
		}

		$actual_step = (int) $_POST[ 'af_actual_step' ];

		// If there was a saved response
		if( isset( $_SESSION[ 'af_response' ][ $ar_form_id ] ) )
		{

			// Merging data
			$merged_response = $_SESSION[ 'af_response' ][ $ar_form_id ];
			if( is_array( $response ) && count( $response ) > 0 )
			{
				foreach( $response AS $key => $answer )
					$merged_response[ $key ] = af_prepare_post_data( $answer );
			}

			$_SESSION[ 'af_response' ][ $ar_form_id ] = $merged_response;
		}
		else
		{
			$merged_response = $response;
		}

		$_SESSION[ 'af_response' ][ $ar_form_id ] = $merged_response;

		$is_submit = false;
		if ( (int) $_POST[ 'af_actual_step' ] == (int) $_POST[ 'af_next_step' ] && ! isset( $_POST[ 'af_submission_back' ] ) ) {
			$is_submit = true;
		}

		// Validate submitted data if user not has gone backwards
		$validation_status = true;
		if ( ! isset( $_POST[ 'af_submission_back' ] ) ) {
			$validation_status = $this->validate( $ar_form_id, $_SESSION[ 'af_response' ][ $ar_form_id ], $actual_step, $is_submit );
		} // Validating response values and setting up error variables

		// If form is finished and user don't have been gone backwards, save data
		if( $is_submit && $validation_status && 0 == count( $af_response_errors ) )
		{

			$form = new AF_Form( $ar_form_id );
			$result_id = $form->save_response( $_SESSION[ 'af_response' ][ $ar_form_id ] );

			if( FALSE != $result_id )
			{
				do_action( 'af_response_save', $result_id );

				unset( $_SESSION[ 'af_response' ][ $ar_form_id ] );
				$_SESSION[ 'af_response' ][ $ar_form_id ][ 'finished' ] = TRUE;

				header( 'Location: ' . $_SERVER[ 'REQUEST_URI' ] );
				die();
			}
		}

		do_action( 'af_process_response_end' );
	}

	/**
	 * Validating response
	 *
	 * @param int   $form_id
	 * @param array $response
	 * @param int   $step
	 * @param bool  $is_submit
	 *
	 * @return boolean $validated
	 * @since 1.0.0
	 */
	public function validate( $form_id, $response, $step, $is_submit )
	{
		global $af_response_errors;

		$elements = $this->form->get_step_elements( $step );
		if ( ! is_array( $elements ) || count( $elements ) == 0 ) {
			return;
		}

		$af_response_errors = array();

		// Running through all elements
		foreach ( $elements AS $element ) {
			if ( $element->splits_form ) {
				continue;
			}

			$answer = '';
			if ( array_key_exists( $element->id, $response ) ) {
				$answer = $response[ $element->id ];
			}

			if ( ! $element->validate( $answer ) ) {

				if ( count( $element->validate_errors ) > 0 ) {
					if ( empty( $af_response_errors[ $element->id ] ) ) {
						$af_response_errors[ $element->id ] = array();
					}

					// Getting every error of element back
					foreach ( $element->validate_errors AS $error ) {
						$af_response_errors[ $element->id ][] = $error;
					}
				}

			}
		}

		$validation_status = count( $af_response_errors ) > 0 ? false : true;

		return apply_filters( 'af_response_validation_status', $validation_status, $form_id, $af_response_errors, $step, $is_submit );
	}
}

/**
 * Checks if a user has participated on a Form
 *
 * @param int  $form_id
 * @param null $user_id
 *
 * @return boolean $has_participated
 */
function af_user_has_participated( $form_id, $user_id = NULL )
{
	global $wpdb, $current_user, $af_global;

	// Setting up user ID
	if( NULL == $user_id )
	{
		get_currentuserinfo();
		$user_id = $user_id = $current_user->ID;
	}

	// Setting up Form ID
	if( NULL == $form_id )
	{
		return FALSE;
	}

	$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$af_global->tables->results} WHERE form_id=%d AND user_id=%s", $form_id, $user_id );

	$count = $wpdb->get_var( $sql );

	if( 0 == $count )
	{
		return FALSE;
	}

	return TRUE;
}
