<?php

/**
 * Load required classes.
 *
 * @author    Paul Kilmurray <paul@kilbot.com>
 *
 * @see      http://wcpos.com
 */

namespace WCPOS\WooCommercePOS;

use const DOING_AJAX;

use WP_HTTP_Response;
use WP_REST_Request;

use WP_REST_Server;

class Init {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// global helper functions
		require_once PLUGIN_PATH . 'includes/wcpos-functions.php';

		// Init hooks
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ), 20 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );

		// Headers for API discoverability
		add_filter( 'rest_pre_serve_request', array( $this, 'rest_pre_serve_request' ), 5, 4 );
		add_action( 'send_headers', array( $this, 'send_headers' ), 10, 1 );

		// Hack - remove when possible
		add_filter( 'woocommerce_rest_shop_order_schema', array( $this, 'shop_order_schema' ), 10, 1 );
		add_filter( 'option_wpseo', array( $this, 'remove_wpseo_rest_api_links' ), 10, 1 );

	}

	/**
	 * Hack to fix the shop order schema.
	 *
	 * @TODO - submit a PR to WooCommerce
	 */
	public function shop_order_schema( array $schema ): array {
		if ( isset( $schema['line_items']['items']['properties']['parent_name']['type'] ) ) {
			if ( 'string' === $schema['line_items']['items']['properties']['parent_name']['type'] ) {
				$schema['line_items']['items']['properties']['parent_name']['type'] = 'mixed';
			}
		}

		return $schema;
	}

	/**
	 * Load the required resources.
	 */
	public function init(): void {
		// common classes
		new i18n();
		new Gateways();
		new Products();
		//      new Customers();
		new Orders();

		// AJAX only
		if ( is_admin() && ( \defined( '\DOING_AJAX' ) && DOING_AJAX ) ) {
			 new AJAX();
		}

		if ( is_admin() && ! ( \defined( '\DOING_AJAX' ) && DOING_AJAX ) ) {
			// admin only
			new Admin();
		} else {
			// frontend only
			new Templates();
			new Form_Handler();
		}

		// load integrations
		$this->integrations();
	}

	/**
	 * Loads the POS API and duck punches the WC REST API.
	 */
	public function rest_api_init(): void {
		if ( woocommerce_pos_request() ) {
			new API();
		}
	}

	/**
	 * Adds 'wcpos' to the query variables allowed before processing.
	 *
	 * Allows (publicly allowed) query vars to be added, removed, or changed prior
	 * to executing the query. Needed to allow custom rewrite rules using your own arguments
	 * to work, or any other custom query variables you want to be publicly available.
	 *
	 * @param string[] $query_vars The array of allowed query variable names.
	 */
	public function query_vars( array $query_vars ): array {
		$query_vars[] = SHORT_NAME;

		return $query_vars;
	}

	/**
	 * Allow pre-flight requests from WCPOS Desktop and Mobile Apps
	 * Note: pre-flight requests cannot have headers, so I can't filter by pos request
	 * See: https://fetch.spec.whatwg.org/#cors-preflight-fetch.
	 *
	 * @param bool             $served  Whether the request has already been served.
	 *                                  Default false.
	 * @param WP_HTTP_Response $result  Result to send to the client. Usually a `WP_REST_Response`.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server  Server instance.
	 *
	 * @return bool $served
	 */
	public function rest_pre_serve_request( bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
		if ( 'OPTIONS' == $request->get_method() ) {
			$allow_headers = array(
				'Authorization',            // For user-agent authentication with a server.
				'X-WP-Nonce',               // WordPress-specific header, used for CSRF protection.
				'Content-Disposition',      // Informs how to process the response data.
				'Content-MD5',              // For verifying data integrity.
				'Content-Type',             // Specifies the media type of the resource.
				'X-HTTP-Method-Override',   // Used to override the HTTP method.
				'X-WCPOS',                  // Used to identify WCPOS requests.
			);

			$server->send_header( 'Access-Control-Allow-Origin', '*' );
			$server->send_header( 'Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE' );
			$server->send_header( 'Access-Control-Allow-Headers', implode( ', ', $allow_headers ) );
		}

		return $served;
	}

	/**
	 * Allow HEAD checks for WP API Link URL and server uptime
	 * Fires once the requested HTTP headers for caching, content type, etc. have been sent.
	 *
	 * FIXME: Why is Link header not exposed sometimes on my development machine?
	 */
	public function send_headers(): void {
		// some server convert HEAD to GET method, so use this query param instead
		if ( isset( $_GET['_method'] ) && 'head' == strtolower( $_GET['_method'] ) ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Expose-Headers: Link' );
		}
	}

	/**
	 * Loads POS integrations with third party plugins.
	 */
	private function integrations(): void {
		//      // WooCommerce Bookings - http://www.woothemes.com/products/woocommerce-bookings/
		//      if ( class_exists( 'WC-Bookings' ) ) {
		//          new Integrations\Bookings();
		//      }
	}

	/**
	 * Yoast SEO adds SEO to the WC REST API by default, this adds to the download weight and can cause problems
	 * It is programmatically turned off here for POS requests
	 * This gets loaded and cached before the rest_api init hook, so we can't use the filter
	 */
	public function remove_wpseo_rest_api_links( $wpseo_options ) {
		if ( woocommerce_pos_request() ) {
			$wpseo_options['remove_rest_api_links'] = true;
			$wpseo_options['enable_headless_rest_endpoints'] = false;
			return $wpseo_options;
		}
		return $wpseo_options;
	}
}
