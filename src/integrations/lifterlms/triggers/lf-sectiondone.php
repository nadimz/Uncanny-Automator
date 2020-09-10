<?php

namespace Uncanny_Automator;

/**
 * Class LF_SECTIONDONE
 * @package Uncanny_Automator
 */
class LF_SECTIONDONE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LF';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LFSECTIONDONE';
		$this->trigger_meta = 'LFSECTION';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LifterLMS */
			'sentence'            => sprintf(  esc_attr__( 'A user completes {{a section:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LifterLMS */
			'select_option_name'  =>  esc_attr__( 'A user completes {{a section}}', 'uncanny-automator' ),
			'action'              => 'lifterlms_section_completed',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'lf_section_done' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->lifterlms->options->all_lf_sections(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $course_id
	 */
	public function lf_section_done( $user_id, $course_id ) {

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
