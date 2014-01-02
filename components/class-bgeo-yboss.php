<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bGeo_Yboss extends bGeo
{
	public $cache_ttl_fail = 1013; // prime numbers make good TTLs
	public $cache_ttl_success = 0; // indefinitely
	public $errors = array();
	public $id_base = 'bgeo-yboss';
	public $woetype = array( // from http://developer.yahoo.com/geo/geoplanet/guide/concepts.html#placetypes
		'7'  => 'town',
		'8'  => 'state-province',
		'9'  => 'county-parish',
		'10' => 'district-ward',
		'11' => 'postcode',
		'12' => 'country',
		'19' => 'region',
		'22' => 'neighborhood-suburb',
		'24' => 'colloquial',
		'29' => 'continent',
		'31' => 'timezone',
	);

	public function __construct()
	{
		if ( is_admin() )
		{
			add_action( 'wp_ajax_bgeo-yboss-placefinder', array( $this, 'placefinder_ajax' ) );
			add_action( 'wp_ajax_bgeo-yboss-placespotter', array( $this, 'placespotter_ajax' ) );
		}
	}

	public function cache_key( $component, $query )
	{
		return md5( $component . serialize( $query ) );
	}

	public function cache_ttl( $code )
	{

		if ( 200 != $code )
		{
			return $cache_ttl_fail;
		}

		return $cache_ttl_success;
	}

	public function placefinder( $query )
	{
		// we're using the placefinder api here
		$api = 'geo/placefinder';

		if ( ! $api_result = wp_cache_get( $this->cache_key( $api, $query ), $this->id_base ) )
		{

			// make sure we're configured
			if (
				! is_object( bgeo()->options()->yahooapi ) ||
				! bgeo()->options()->yahooapi->key ||
				! bgeo()->options()->yahooapi->secret
			)
			{
				$this->errors[] = new WP_Error( 'api_request_error', 'No API key or secret configured.', FALSE );
				return FALSE;
			}

			$args = array();
			$args['q'] = $query;

			// set the output to JSON
			$args['flags'] = 'J';

			// basic request data
			$url = 'http://yboss.yahooapis.com/' . $api;

			// construct a request signiture
			if ( ! class_exists( 'OAuthConsumer' ) )
			{
				require_once __DIR__ . '/external/OAuth.php';
			}
			$consumer = new OAuthConsumer( bgeo()->options()->yahooapi->key, bgeo()->options()->yahooapi->secret );
			$request = OAuthRequest::from_consumer_and_token( $consumer, NULL, 'GET', $url, $args );
			$request->sign_request( new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL );

			// get the authorization header
			$headers = array( $request->to_header() );

			// do the request
			$api_result = wp_remote_get(
				sprintf( '%s?%s', $url, OAuthUtil::build_http_query( $args ) ),
				array(
					'headers' => array(
						'Authorization' => str_ireplace( 'Authorization: ', '', $headers[0] )
					)
				)
			);

			wp_cache_set( $this->cache_key( $api, $query ), $api_result, $this->id_base, $this->cache_ttl( wp_remote_retrieve_response_code( $api_result ) ) );
		}

		// did the API return a valid response code?
		if ( 200 != wp_remote_retrieve_response_code( $api_result ) )
		{
			$this->errors[] = new WP_Error(
				'api_response_error', 'The endpoint returned a non-200 response. This response may have been cached.',
				wp_remote_retrieve_response_code( $api_result ) . wp_remote_retrieve_response_message( $api_result )
			);
			return FALSE;
		}

		// did we get a result that makes sense?
		$api_result = json_decode( wp_remote_retrieve_body( $api_result ) );

		if ( ! is_array( $api_result->bossresponse->placefinder->results ) )
		{
			$this->errors[] = new WP_Error( 'api_response_error', 'The endpoint didn\'t return a meaningful result. This response may have been cached.', $api_result );
			return FALSE;
		}

		return $api_result->bossresponse->placefinder->results;
	}

	public function placefinder_ajax( $query )
	{

		$query = 'Aberdeen -98.522563831024 45.444191924201';
		$query = 'NYC';
		$query = 'California';
		$query = 'Europe';
		$query = 'London';
		$query = 'Scotland';
		$query = 'Utah';
		$query = 'Midwest';
		$query = 'West Coast';
		$query = 'New England';
		$query = 'financial district, san francisco';

		echo '<pre>';

		print_r( $this->placefinder( $query ) );
		die;
	}

	public function placespotter_ajax()
	{
		// http://developer.yahoo.com/boss/geo/docs/key-concepts.html
		// http://developer.yahoo.com/boss/geo/docs/codeexamples.html#oauth_php
	}

}//end bGeo_Yboss class