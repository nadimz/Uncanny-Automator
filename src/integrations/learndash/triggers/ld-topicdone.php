<?php

namespace Uncanny_Automator;

/**
 * Class LD_TOPICDONE
 * @package Uncanny_Automator
 */
class LD_TOPICDONE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LD_TOPICDONE';
		$this->trigger_meta = 'LDTOPIC';
		$this->define_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$args = [
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$course_options = $uncanny_automator->helpers->recipe->options->wp_query( $args, true, __( 'Any course', 'uncanny-automator' ) );

		$args = [
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$lesson_options         = $uncanny_automator->helpers->recipe->options->wp_query( $args, true, __( 'Any course', 'uncanny-automator' ) );
		$course_relevant_tokens = [
			'LDCOURSE'     => __( 'Course title', 'uncanny-automator' ),
			'LDCOURSE_ID'  => __( 'Course ID', 'uncanny-automator' ),
			'LDCOURSE_URL' => __( 'Course URL', 'uncanny-automator' ),
		];
		$lesson_relevant_tokens = [
			'LDLESSON'     => __( 'Lesson title', 'uncanny-automator' ),
			'LDLESSON_ID'  => __( 'Lesson ID', 'uncanny-automator' ),
			'LDLESSON_URL' => __( 'Lesson URL', 'uncanny-automator' ),
		];
		$relevant_tokens        = [
			$this->trigger_meta          => __( 'Topic title', 'uncanny-automator' ),
			$this->trigger_meta . '_ID'  => __( 'Topic ID', 'uncanny-automator' ),
			$this->trigger_meta . '_URL' => __( 'Topic URL', 'uncanny-automator' ),
		];
		$trigger                = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf( __( 'A user completes {{a topic:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => __( 'A user completes {{a topic}}', 'uncanny-automator' ),
			'action'              => 'learndash_topic_completed',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'topic_completed' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
			'options_group'       => [
				$this->trigger_meta => [
					$uncanny_automator->helpers->recipe->field->select_field_ajax(
						'LDCOURSE',
						__( 'Course', 'uncanny-automator' ),
						$course_options,
						'',
						'',
						false,
						true,
						[
							'target_field' => 'LDLESSON',
							'endpoint'     => 'select_lesson_from_course_LD_TOPICDONE',
						],
						$course_relevant_tokens
					),
					$uncanny_automator->helpers->recipe->field->select_field_ajax(
						'LDLESSON',
						__( 'Lesson', 'uncanny-automator' ),
						$lesson_options,
						'',
						'',
						false,
						true,
						[
							'target_field' => 'LDTOPIC',
							'endpoint'     => 'select_topic_from_lesson_LD_TOPICDONE',
						],
						$lesson_relevant_tokens
					),
					$uncanny_automator->helpers->recipe->field->select_field( 'LDTOPIC', __( 'Topic', 'uncanny-automator' ), [], false, false, false, $relevant_tokens ),
				],
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function topic_completed( $data ) {

		if ( empty( $data ) ) {
			return;
		}

		global $uncanny_automator;

		$user   = $data['user'];
		$topic  = $data['topic'];
		$lesson = $data['lesson'];
		$course = $data['course'];

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $topic->ID,
			'user_id' => $user->ID,
		];

		$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );
		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					$uncanny_automator->insert_trigger_meta(
						[
							'user_id'        => $user->ID,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'LDCOURSE',
							'meta_value'     => $course->ID,
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						]
					);
					$uncanny_automator->insert_trigger_meta(
						[
							'user_id'        => $user->ID,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'LDLESSON',
							'meta_value'     => $lesson->ID,
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						]
					);
					$uncanny_automator->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}


}
