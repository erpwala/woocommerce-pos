<?php


namespace WCPOS\WooCommercePOS\API;

use WP_REST_Request;

class Products {
	private $request;

	/**
	 * Products constructor.
	 *
	 * @param $request WP_REST_Request
	 */
	public function __construct( WP_REST_Request $request ) {
		$this->request = $request;

		add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'product_response' ), 10, 3 );
		add_filter( 'woocommerce_rest_product_object_query', array( $this, 'product_query' ), 10, 2 );
		add_filter( 'posts_search', array( $this, 'posts_search' ), 10, 2 );
	}

	/**
	 * Filter the product response
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WC_Data $product Product data.
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response $response The response object.
	 */
	public function product_response( \WP_REST_Response $response, \WC_Data $product, \WP_REST_Request $request ): \WP_REST_Response {
		// get the old product data
		$data = $response->get_data();

		// set thumbnail
		$data['thumbnail'] = $this->get_thumbnail( $product->get_id() );

		// reset the new response data
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Filter the query arguments for a request.
	 *
	 * @param array $args Key value array of query var to query value.
	 * @param \WP_REST_Request $request The request used.
	 *
	 * @return array $args Key value array of query var to query value.
	 */
	public function product_query( array $args, WP_REST_Request $request ): array {
		if ( isset( $request['date_modified_gmt_after'] ) ) {
			$date_query = array(
				'column' => 'post_modified_gmt',
				'after'  => $request['date_modified_gmt_after'],
			);
			array_push( $args['date_query'], $date_query );
		}

		return $args;
	}

	/**
	 * Returns thumbnail if it exists, if not, returns the WC placeholder image
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	private function get_thumbnail( int $id ): string {
		$image    = false;
		$thumb_id = get_post_thumbnail_id( $id );

		if ( $thumb_id ) {
			$image = wp_get_attachment_image_src( $thumb_id, 'shop_thumbnail' );
		}

		if ( is_array( $image ) ) {
			return $image[0];
		}

		return wc_placeholder_img_src();
	}


	/**
	 * Search SQL filter for matching against post title only.
	 *
	 * @param string $search
	 * @param \WP_Query $wp_query
	 */
	function posts_search( $search, $wp_query ): string {
		global $wpdb;
		if ( empty( $search ) ) {
			return $search; // skip processing - no search term in query
		}
		$q      = $wp_query->query_vars;
		$n      = ! empty( $q['exact'] ) ? '' : '%';
		$search =
		$searchand = '';
		foreach ( (array) $q['search_terms'] as $term ) {
			$term      = esc_sql( $wpdb->esc_like( $term ) );
			$search    .= "{$searchand}($wpdb->posts.post_title LIKE '{$n}{$term}{$n}')";
			$searchand = ' AND ';
		}
		if ( ! empty( $search ) ) {
			$search = " AND ({$search}) ";
			if ( ! is_user_logged_in() ) {
				$search .= " AND ($wpdb->posts.post_password = '') ";
			}
		}

		return $search;
	}

	/**
	 * Returns array of all product ids, name
	 *
	 * @param array $fields
	 *
	 * @return array|void
	 */
	public function get_all_posts( array $fields = array() ) {
		global $wpdb;

		$all_posts = $wpdb->get_results( '
			SELECT ID as id, post_title as name FROM ' . $wpdb->posts . '
			WHERE post_status = "publish" AND post_type = "product"
        ' );

		// wpdb returns id as string, we need int
		return array_map( array( $this, 'format_id' ), $all_posts );
	}

	/**
	 *
	 *
	 * @param object $record
	 *
	 * @return object
	 */
	private function format_id( $record ) {
		$record->id = (int) $record->id;

		return $record;
	}
}