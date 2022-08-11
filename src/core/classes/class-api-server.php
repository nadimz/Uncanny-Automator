<?php

namespace Uncanny_Automator;

/**
 * Class Api.
 *
 * @package Uncanny_Automator
 */
class Api_Server {

	public static $url;

	public static $mock_response = null;

	private static $instance = null;

	/**
	 * __construct
	 *
	 * @return void
	 */
	private function __construct() {

		self::$url = apply_filters( 'automator_api_url', AUTOMATOR_API_URL );

		add_filter( 'http_request_args', array( $this, 'add_api_headers' ), 10, 2 );
		add_filter( 'automator_trigger_should_complete', array( $this, 'maybe_log_trigger' ), 10, 3 );

	}

	/**
	 * get_instance
	 *
	 * @return Api_server
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new Api_server();
		}

		return self::$instance;

	}

	/**
	 * Method add_api_headers
	 *
	 * @param array $args
	 * @param string $request_url
	 *
	 * @return array
	 */
	public function add_api_headers( $args, $request_url ) {

		$license_key = self::get_license_key();

		if ( ! $license_key ) {
			return $args;
		}

		// If the request URL starts with the Automator API url
		if ( substr( $request_url, 0, strlen( self::$url ) ) === self::$url ) {
			$args['headers']['license-key'] = $license_key;
			$args['headers']['site-name']   = self::get_site_name();
			$args['headers']['item-name']   = self::get_item_name();
		}

		return $args;
	}

	/**
	 * Method get_license_type
	 *
	 * @return string
	 */
	public static function get_license_type() {
		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === get_option( 'uap_automator_pro_license_status' ) ) {
			return 'pro';
		} elseif ( 'valid' === get_option( 'uap_automator_free_license_status' ) ) {
			return 'free';
		}

		return false;
	}

	/**
	 * Method get_license_key
	 *
	 * @return string
	 */
	public static function get_license_key() {
		$license_type = self::get_license_type();

		return get_option( 'uap_automator_' . $license_type . '_license_key' );
	}

	/**
	 * Method get_item_name
	 *
	 * @return string
	 */
	public static function get_item_name() {

		$license_type = strtoupper( self::get_license_type() );

		if ( ! $license_type ) {
			return '';
		}

		if ( 'PRO' === $license_type ) {
			if ( defined( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' ) ) {
				return constant( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' );
			} elseif ( defined( 'AUTOMATOR_AUTOMATOR_' . $license_type . '_ITEM_NAME' ) ) {
				return constant( 'AUTOMATOR_AUTOMATOR_' . $license_type . '_ITEM_NAME' );
			}
		}

		return constant( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' );
	}

	/**
	 * Method get_site_name
	 *
	 * @return string
	 */
	public static function get_site_name() {
		return preg_replace( '(^https?://)', '', get_home_url() );
	}

	/**
	 * Method add_endpoint_parts
	 *
	 * @param array $params
	 *
	 * @return array $params
	 */
	public function add_endpoint_parts( $params ) {

		$endpoint_parts = explode( '/', $params['endpoint'] );

		if ( 2 === count( $endpoint_parts ) ) {
			$params['api_version'] = array_shift( $endpoint_parts );
			$params['integration'] = array_shift( $endpoint_parts );
		}

		return $params;
	}

	/**
	 * Method filter_params
	 *
	 * @param array $params
	 *
	 * @return array $params
	 */
	public function filter_params( $params ) {

		$params = apply_filters( 'automator_api_call', $params );

		if ( ! empty( $params['integration'] ) ) {
			$params = apply_filters( 'automator_' . $params['integration'] . '_api_call', $params );

			if ( ! empty( $params['body']['action'] ) ) {
				$params = apply_filters( 'automator_' . $params['integration'] . '_' . $params['body']['action'] . '_api_call', $params );
			}
		}

		return $params;
	}

	/**
	 * api_call
	 *
	 * @param string $endpoint
	 * @param array $body
	 *
	 * @return void
	 */
	public static function api_call( $params ) {

		$api = self::get_instance();

		if ( null !== self::$mock_response ) {
			return self::$mock_response;
		}

		if ( empty( $params['endpoint'] ) ) {
			throw new \Exception( 'Endpoint is required', 500 );
		}

		if ( empty( $params['body'] ) ) {
			throw new \Exception( 'Request body is required', 500 );
		}

		$params = $api->add_endpoint_parts( $params );

		$params['method']             = 'POST';
		$params['url']                = self::$url . $params['endpoint'];
		$params['body']['plugin_ver'] = InitializePlugin::PLUGIN_VERSION;

		$params = $api->filter_params( $params );

		$response = self::call( $params );

		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = $api->get_response_body( $response, $code );

		$api->maybe_throw_exception( $response_body, $code );

		if ( isset( $response_body['statusCode'] ) && array_key_exists( 'data', $response_body ) ) {
			return $response_body;
		}

		automator_log( var_export( $response_body, true ), 'Unrecognized API response: ' ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

		throw new \Exception( 'Unrecognized API response', 500 );
	}

	/**
	 * Method maybe_throw_exception
	 *
	 * @param array $response_body The response body.
	 * @param integer $code The HTTP Status code.
	 *
	 * @return void
	 * @throws Exception If there is an error with the response.
	 *
	 */
	private function maybe_throw_exception( $response_body = array(), $code = 200 ) {

		if ( ! is_array( $response_body ) ) {
			automator_log( var_export( $response_body, true ), 'Invalid API response: ' ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			throw new \Exception( 'Invalid API response', 500 );
		}

		// Handle zero credits from client with upgrade to link.
		if ( 402 === $code && false !== strpos( $response_body['error']['description'], 'Upgrade to Uncanny Automator Pro' ) ) {
			throw new \Exception( 'Credit required for action/trigger. Current credits: 0. {{automator_upgrade_link}}.', 402 );
		}

		if ( isset( $response_body['error'] ) && isset( $response_body['error']['description'] ) ) {
			$error = $response_body['error']['description'];
			automator_log( $error, 'api_call returned an error: ' );
			throw new \Exception( $error, $response_body['statusCode'] );
		}

		// Handle response body that has [data][error][message] (e.g. Instagram user media publish limit exceeded).
		if ( isset( $response_body['data']['error'] ) && isset( $response_body['data']['error']['message'] ) ) {
			throw new \Exception( 'API has responded with an error message: ' . $response_body['data']['error']['message'], $response_body['statusCode'] );
		}

	}

	/**
	 * call
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $body
	 * @param array $action
	 *
	 * @return mixed $response
	 */
	public static function call( $params ) {

		$api = self::get_instance();

		if ( empty( $params['method'] ) ) {
			throw new \Exception( 'Request method is required', 500 );
		}

		if ( empty( $params['url'] ) ) {
			throw new \Exception( 'URL is required', 500 );
		}

		$request = array();

		$request = $api->maybe_add_optional_params( $request, $params );

		$request = apply_filters( 'automator_call', $request, $params );

		$time_before = microtime( true );

		$response = wp_remote_request(
			$params['url'],
			$request
		);

		$time_spent = round( ( microtime( true ) - $time_before ) * 1000 );

		$params['time_spent'] = $time_spent;

		$api->maybe_log_action( $params, $request, $response );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'WordPress was not able to make a request: ' . $response->get_error_message(), 500 );
		}

		return $response;
	}

	/**
	 * maybe_add_optional_params
	 *
	 * @param mixed $request
	 * @param mixed $params
	 *
	 * @return void
	 */
	public function maybe_add_optional_params( $request, $params ) {

		$optional_params = array(
			'method',
			'body',
			'timeout',
			'redirection',
			'httpversion',
			'user-agent',
			'reject_unsafe_urls',
			'blocking',
			'headers',
			'cookies',
			'compress',
			'decompress',
			'sslverify',
			'sslcertificates',
			'stream',
			'filename',
			'limit_response_size',
		);

		foreach ( $optional_params as $optional_param ) {
			if ( isset( $params[ $optional_param ] ) ) {
				$request[ $optional_param ] = $params[ $optional_param ];
			}
		}

		return $request;
	}

	/**
	 * get_license
	 *
	 * @return mixed false||array
	 */
	public static function get_license() {

		$cached_license = get_transient( 'automator_api_license' );

		if ( false !== $cached_license ) {
			return $cached_license;
		}

		$params = array(
			'endpoint' => 'v2/credits',
			'body'     => array(
				'action' => 'get_credits',
			),
		);

		try {
			$response = self::api_call( $params );
			$license  = $response['data'];
			set_transient( 'automator_api_license', $license, MINUTE_IN_SECONDS );

			return $license;
		} catch ( \Exception $e ) {
			throw new \Exception( __( 'Unable to fetch the license: ', 'uncanny-automator' ) . $e->getMessage() );
		}
	}

	/**
	 * has_valid_license
	 *
	 * @return mixed false||array
	 */
	public static function has_valid_license() {

		$license = self::get_license();

		if ( ! isset( $license['license'] ) || 'valid' !== $license['license'] ) {
			throw new \Exception( __( 'License is not valid', 'uncanny-automator' ) );
		}

		return $license;
	}

	/**
	 * has_credits
	 *
	 * @return bool
	 */
	public static function has_credits() {

		$license = self::has_valid_license();

		if ( 'Uncanny Automator Pro' === $license['item_name'] ) {
			return true;
		}

		if ( intval( $license['paid_usage_count'] ) >= intval( $license['usage_limit'] ) ) {
			throw new \Exception( __( 'Not enough credits', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * charge_credit
	 *
	 * @return mixed false||array
	 */
	public function charge_usage( $trigger_data = null ) {

		$license = array();

		self::has_credits();

		$params = array(
			'endpoint' => 'v2/credits',
			'body'     => array(
				'action' => 'reduce_credits',
			),
		);

		$license = self::api_call( $params );

		set_transient( 'automator_api_license', $license['data'], MINUTE_IN_SECONDS );

		return $license;

	}

	/**
	 * create_payload
	 *
	 * @param mixed $body
	 * @param mixed $code
	 *
	 * @return void
	 */
	public function create_payload( $body = null, $code = null, $error = null ) {

		$payload = array(
			'data'       => $body,
			'statusCode' => $code,
		);

		if ( null !== $error ) {
			$payload['error'] = array( 'description' => $error );
		}

		return $payload;
	}

	/**
	 * Will log an action in the action meta.
	 */
	public function maybe_log_action( $params, $request, $response ) {

		if ( ! isset( $params['action'] ) ) {
			return;
		}

		$credits = $this->get_response_credits( $response );

		$log = array(
			'type'          => 'action',
			'recipe_log_id' => $params['action']['recipe_log_id'],
			'item_log_id'   => $params['action']['action_log_id'],
			'endpoint'      => $params['endpoint'],
			'params'        => maybe_serialize( $params ),
			'request'       => maybe_serialize( $request ),
			'response'      => maybe_serialize( $response ),
			'balance'       => isset( $credits['balance'] ) ? $credits['balance'] : null,
			'price'         => isset( $credits['price'] ) ? $credits['price'] : null,
			'status'        => $this->get_response_code( $response ),
			'time_spent'    => isset( $params['time_spent'] ) ? $params['time_spent'] : 0,
		);

		Automator()->db->api->add( $log );
	}

	public function get_response_credits( $response ) {

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $response_body['credits'] ) ) {
			return null;
		}

		return $response_body['credits'];

	}

	public function get_response_code( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_code();
		}

		return wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Method get_response_body
	 *
	 * @param array $response
	 * @param int $code
	 *
	 * @return array
	 */
	public function get_response_body( $response, $code ) {

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		//Some endpoints like Mailchimp send a NULL as the reponse with a code in case of success.
		if ( empty( $response_body ) && ! empty( $code ) ) {
			return $this->create_payload( null, $code );
		}

		return $response_body;
	}

	public function maybe_log_trigger( $process_further, $args, $trigger ) {

		if ( ! $trigger->get_uses_api() ) {
			return $process_further;
		}

		$log_entry = $args['trigger_entry'];

		$log = array(
			'type'          => 'trigger',
			'recipe_log_id' => $log_entry['recipe_log_id'],
			'item_log_id'   => $log_entry['trigger_log_id'],
			'params'        => $args['trigger_args'],
			'balance'       => isset( $credits['balance'] ) ? $credits['balance'] : null,
			'price'         => isset( $credits['price'] ) ? $credits['price'] : null,
		);

		if ( ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
			try {
				$api_response   = $this->charge_usage();
				$credits        = $api_response['credits'];
				$log['balance'] = isset( $credits['balance'] ) ? $credits['balance'] : null;
				$log['price']   = isset( $credits['price'] ) ? $credits['price'] : null;
			} catch ( \Exception $e ) {
				$process_further = false;
			}
		}

		Automator()->db->api->add( $log );

		return $process_further;

	}

	/**
	 * add_trigger_meta
	 *
	 * @param array $params
	 * @param array $log
	 *
	 * @return void
	 */
	public function add_trigger_meta( $args, $log ) {

		$log_entry = $args['trigger_entry'];

		$trigger_id     = $log_entry['trigger_id'];
		$trigger_log_id = $log_entry['trigger_log_id'];
		$run_number     = $log_entry['run_number'];

		$args = array(
			'user_id'    => $log_entry['user_id'],
			'meta_key'   => 'api_log',
			'meta_value' => maybe_serialize( $log ),
			'run_time'   => $run_number,
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );
	}
}

Api_Server::get_instance();
