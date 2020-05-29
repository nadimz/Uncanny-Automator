<?php

namespace Uncanny_Automator;


/**
 * Class PMP_MEMBERSHIPPURCH
 * @package Uncanny_Automator
 */
class PMP_MEMBERSHIPPURCH {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'PMP';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'PMPMEMBERSHIPPURCH';
		$this->trigger_meta = 'PMPMEMBERSHIP';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$options = $uncanny_automator->helpers->recipe->paid_memberships_pro->options->all_memberships( __( 'Membership', 'uncanny-automator' ) );

		$options['options'] = array( '-1' => __( 'Any membership', 'uncanny-automator' ) ) + $options['options'];

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Paid Memberships Pro */
			'sentence'            => sprintf( __( 'A user purchases {{a membership:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Paid Memberships Pro */
			'select_option_name'  => __( 'A user purchases {{a membership}}', 'uncanny-automator' ),
			'action'              => 'pmpro_after_checkout',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'pmpro_payment_completed' ),
			'options'             => [
				$options,
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param \MemberOrder $morder
	 */
	public function pmpro_payment_completed( $user_id, \MemberOrder $morder ) {
		global $uncanny_automator;

		if ( ! $morder instanceof \MemberOrder ) {
			return;
		}

		$user                = $morder->getUser();
		$membership          = $morder->getMembershipLevel();
		$user_id             = $user->ID;
		$membership_id       = $membership->id;
		$recipes             = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		$required_membership = $uncanny_automator->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids  = [];

		//Add where option is set to Any membership
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all memberships
				if ( - 1 === intval( $required_membership[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];

					break;
				}
			}
		}

		//Add where membership ID is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all memberships
				if ( $required_membership[ $recipe_id ][ $trigger_id ] == $membership_id ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$args = [
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				];

				$result = $uncanny_automator->maybe_add_trigger_entry( $args, false );

				if ( $result ) {
					foreach ( $result as $r ) {
						if ( true === $r['result'] ) {
							do_action( 'uap_save_pmp_membership_level', $membership_id, $r['args'], $user_id, $this->trigger_meta );
							$uncanny_automator->maybe_trigger_complete( $r['args'] );
						}
					}
				}
			}
		}

		return;
	}
}