<?php

namespace Uncanny_Automator;

use WP_User;

/**
 * Class WP_CREATE_ROLE
 *
 * @package Uncanny_Automator
 */
class WP_CREATE_ROLE {

	use Recipe\Action_Tokens;

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'CREATE_ROLE';
		$this->action_meta = 'WPROLE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wordpress-core/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			/* translators: Action - WordPress */
			'sentence'           => sprintf( esc_attr__( "Create {{a new role:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' => esc_attr__( "Create {{a new role}} role", 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'create_role' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		Automator()->helpers->recipe->wp->options->load_options = true;

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group'      => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->field->text_field( 'WPROLE', esc_attr__( 'Role', 'uncanny-automator' ), true, 'text', '', true, '' ),

						Automator()->helpers->recipe->field->text_field( 'WPROLE_DISPLAY_NAME', esc_attr__( 'Role display name', 'uncanny-automator' ), true, 'text', '', true, '' )
					),
				),
			)
		);

		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function create_role( $user_id, $action_data, $recipe_id, $args ) {
		if ( isset( $action_data['meta']['WPROLE'] ) ) {
			$role_name = Automator()->parse->text( $action_data['meta']['WPROLE'], $recipe_id, $user_id, $args );			
		} else {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( 0, $action_data, $recipe_id, esc_attr__( 'Role was not set', 'uncanny-automator' ) );
			
			return;
		}

		if ( isset( $action_data['meta']['WPROLE_DISPLAY_NAME'] ) ) {
			$role_display_name = Automator()->parse->text( $action_data['meta']['WPROLE_DISPLAY_NAME'], $recipe_id, $user_id, $args );
		} else {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( 0, $action_data, $recipe_id, esc_attr__( 'Role display name was not set', 'uncanny-automator' ) );
			
			return;
		}

		if ( ! add_role( $role_name, $role_display_name) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( 0, $action_data, $recipe_id, sprintf( esc_attr__( 'Role already exists: %1$s', 'uncanny-automator' ), $role_name ) );
			return;
		}

		Automator()->complete->user->action( $user_id, $action_data, $recipe_id );
	}
}
