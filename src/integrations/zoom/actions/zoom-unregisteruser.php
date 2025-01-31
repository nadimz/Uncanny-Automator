<?php

namespace Uncanny_Automator;

/**
 * Class ZOOM_UNREGISTERUSER
 *
 * @package Uncanny_Automator
 */
class ZOOM_UNREGISTERUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ZOOM';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ZOOMUNREGISTERUSER';
		$this->action_meta = 'ZOOMMEETING';
		$this->helpers     = new Zoom_Helpers();
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/zoom/' ),
			'is_pro'                => false,
			//'is_deprecated'      => true,
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			/* translators: Meeting topic */
			'sentence'              => sprintf( __( 'Remove the user from {{a meeting:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => __( 'Remove the user from {{a meeting}}', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'execution_function'    => array( $this, 'zoom_unregister_user' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$account_users_field = array(
			'option_code'           => 'ZOOMUSER',
			'label'                 => __( 'Account user', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'is_ajax'               => true,
			'endpoint'              => 'uap_zoom_api_get_meetings',
			'fill_values_in'        => $this->action_meta,
			'options'               => $this->helpers->get_account_user_options(),
			'relevant_tokens'       => array(),
			'supports_custom_value' => false,
		);

		$user_meetings_field = array(
			'option_code'           => $this->action_meta,
			'label'                 => __( 'Meeting', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_tokens'       => false,
			'supports_custom_value' => false,
		);

		$option_fileds = array(
			$account_users_field,
			$user_meetings_field,
		);

		//Don't show the user dropdown to old credentials so it's easier to test the update
		if ( $this->helpers->jwt_mode() ) {
			$option_fileds = array(
				$this->helpers->get_meetings_field(),
			);
		}

		return array(
			'options_group' => array(
				$this->action_meta => $option_fileds,
			),
		);
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function zoom_unregister_user( $user_id, $action_data, $recipe_id, $args ) {

		try {

			if ( empty( $user_id ) ) {
				throw new \Exception( __( 'User was not found.', 'uncanny-automator' ) );
			}

			$meeting_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

			if ( empty( $meeting_key ) ) {
				throw new \Exception( __( 'Meeting was not found.', 'uncanny-automator' ) );
			}

			$meeting_key = str_replace( '-objectkey', '', $meeting_key );

			$user  = get_userdata( $user_id );
			$email = $user->user_email;

			$result = $this->helpers->unregister_user( $email, $meeting_key, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
