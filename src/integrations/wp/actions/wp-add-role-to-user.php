<?php

namespace Uncanny_Automator;

use WP_User;

/**
 * Class GEN_ADDROLE
 *
 * @package Uncanny_Automator
 */
class WP_ADD_ROLE_TO_USER {

	use Recipe\Action_Tokens;

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $action_code;
	private $action_meta;
	private $user;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ADD_ROLE_TO_USER';
		$this->action_meta = 'WPROLE';
		$this->user        = 'WPUSER';
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
			'sentence'           => sprintf( esc_attr__( 'Add {{role:%1$s}} to a user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' => esc_attr__( 'Add a {{role}} to a user', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_role' ),
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
				'options_group' => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->field->text_field( $this->action_meta, esc_attr__( 'Role', 'uncanny-automator' ), true, 'text', '', false, '' ),
						Automator()->helpers->recipe->field->text_field( $this->user, esc_attr__( 'Username', 'uncanny-automator' ), true, 'text', '', true, esc_attr__( 'Only alphanumeric, _, space, ., -, @', 'uncanny-automator' ) ),
					)
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
	public function add_role( $user_id, $action_data, $recipe_id, $args ) {
		
		$role     = sanitize_text_field( Automator()->parse->text( $action_data['meta'][$this->action_meta], $recipe_id, $user_id, $args ) );
		$username = sanitize_text_field( Automator()->parse->text( $action_data['meta'][$this->user], $recipe_id, $user_id, $args ) );

		file_put_contents('php://stderr', 'NADIM: Roles is: '. print_r($role, TRUE) . ' Username: ' . print_r($username, TRUE));

		$user = get_user_by('login', $username);
		if ( $user instanceof WP_User ) {
			$user->add_role( $role );

			// Hydrate the tokens with value.
			$this->hydrate_tokens(
				array(
					'USER_ROLES' => ! empty( $user->roles ) ? implode( ', ', array_values( $user->roles ) ) : '',
				)
			);

			Automator()->complete->user->action( $user_id, $action_data, $recipe_id );

			return;
		}

		Automator()->complete->action( 0, $action_data, $recipe_id, esc_attr__( 'Cannot find username: %1$s', 'uncanny-automator' ) , $username);
	}
}
