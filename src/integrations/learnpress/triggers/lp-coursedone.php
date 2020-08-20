<?php

namespace Uncanny_Automator;

/**
 * Class LP_COURSEDONE
 * @package Uncanny_Automator
 */
class LP_COURSEDONE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LPCOURSEDONE';
		$this->trigger_meta = 'LPCOURSE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name(),
			'support_link'        => $uncanny_automator->get_author_support_link(),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnPress */
			'sentence'            => sprintf(  esc_attr__( 'A user completes {{a course:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnPress */
			'select_option_name'  =>  esc_attr__( 'A user completes {{a course}}', 'uncanny-automator' ),
			'action'              => 'learn-press/user-course-finished',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'lp_course_done' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->learnpress->options->all_lp_courses(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $course_id
	 * @param $user_id
	 * @param $result
	 */
	public function lp_course_done( $course_id, $user_id, $result ) {

		if ( empty( $user_id ) ) {
			return;
		}

		global $uncanny_automator;

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $course_id ),
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
