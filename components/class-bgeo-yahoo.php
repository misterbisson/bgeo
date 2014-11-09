<?php
/*
This class includes the yahoo API getters.
*/

class bGeo_Yahoo
{
	public $cache_ttl_fail = 1013; // prime numbers make good TTLs
	public $cache_ttl_success = 0; // indefinitely
	public $errors = array();
	public $id_base = 'bgeo-yahoo';
	public $woetype = array( // from http://developer.yahoo.com/geo/geoplanet/guide/concepts.html#placetypes
		'7'  => 'town',
		'8'  => 'state-province',
		'9'  => 'county-parish',
		'10' => 'district-ward',
		'11' => 'postcode',
		'12' => 'country',
		'13' => 'island',
		'14' => 'airport',
		'15' => 'river-canal-lake-bay-ocean',
		'16' => 'park-mountain-beach',
		'17' => 'misc',
		'19' => 'region',
		'20' => 'point-of-interest',
		'22' => 'neighborhood-suburb',
		'24' => 'colloquial',
		'25' => 'zone',
		'26' => 'historical-state',
		'27' => 'historical-county',
		'29' => 'continent',
		'31' => 'timezone',
		'33' => 'estate',
		'35' => 'historical-town',
		'37' => 'ocean',
		'38' => 'sea',
	);
	public $woetype_rank = array(
		'11' => 20,
		'22' => 18,
		'7'  => 16,
		'10' => 14,
		'9'  => 12,
		'8'  => 10,
		'24' => 8,
		'12' => 6,
		'29' => 4,
		'19' => 2,
		'31' => 0,
	);

	public function __construct()
	{
		if ( is_admin() )
		{
			add_action( 'wp_ajax_bgeo-yahoo-boss', array( $this, 'boss_ajax' ) );
			add_action( 'wp_ajax_bgeo-yahoo-placefinder', array( $this, 'placefinder_ajax' ) );
			add_action( 'wp_ajax_bgeo-yahoo-placespotter', array( $this, 'placespotter_ajax' ) );
			add_action( 'wp_ajax_bgeo-yahoo-yql', array( $this, 'yql_ajax' ) );
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

		return $this->cache_ttl_success;
	}

	public function boss( $query, $api = 'web' )
	{

		$multiple = FALSE;
		if ( is_array( $api ) )
		{
			$multiple = TRUE;
			$api = implode( ',', $api );
		}

		$args = wp_parse_args(
			is_array( $query ) ? $query : array(),
			array(
				'q' => is_array( $query ) ? $query['q'] : $query,
				'format' => 'json',
			)
		);

		if ( ! $api_result = wp_cache_get( $this->cache_key( $api, $args ), $this->id_base ) )
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

			// basic request data
			$url = 'http://yboss.yahooapis.com/ysearch/' . $api;

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

		if ( ! isset( $api_result->bossresponse ) )
		{
			$this->errors[] = new WP_Error( 'api_response_error', 'The endpoint didn\'t return a meaningful result. This response may have been cached.', $api_result );
			return FALSE;
		}

		if ( $multiple )
		{
			return $api_result->bossresponse;
		}
		else
		{
			return $api_result->bossresponse->$api->results;
		}
	}

	public function boss_ajax()
	{
die;

		$query = 'NEW SIBERIAN ISLANDS site:en.wikipedia.org';

		echo '<pre>';

		print_r( $this->boss( array( 'q' => $query ), 'limitedweb' ) );
		print_r( $this->errors );

		die;
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

			if ( is_array( $query ) )
			{
				$args = $query;
			}
			else
			{
				$args = array();
				$args['q'] = $query;
			}

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
			$formatted_url = sprintf( '%s?%s', $url, OAuthUtil::build_http_query( $args ) );
			$api_result = wp_remote_get(
				$formatted_url,
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

	public function placefinder_ajax()
	{
die;

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
		print_r( $this->errors );

		die;
	}

	public function placespotter_ajax()
	{
		// http://developer.yahoo.com/boss/geo/docs/key-concepts.html
		// http://developer.yahoo.com/boss/geo/docs/codeexamples.html#oauth_php
	}

	public function yql( $query )
	{
		// we're using the yql api here
		$api = 'v1/public/yql';

		if ( ! $api_result = wp_cache_get( $this->cache_key( $api, $query ), $this->id_base ) )
		{
			$args = array();
			$args['q'] = $query;

			// set the output to JSON
			$args['format'] = 'json';

			// basic request data
			$url = 'http://query.yahooapis.com/' . $api;

			// do the request
			$api_result = wp_remote_get( sprintf(
				'%s?%s',
				$url,
				http_build_query( $args )
			) );

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

		if ( ! isset( $api_result->query->results ) )
		{
			$this->errors[] = new WP_Error( 'api_response_error', 'The endpoint didn\'t return a meaningful result. This response may have been cached.', $api_result );
			return FALSE;
		}

		return $api_result->query->results;
	}

	public function yql_ajax()
	{
die;

		$query = 'SELECT * FROM geo.placetypes(0)';
		$query = 'SELECT * FROM geo.places.belongtos WHERE member_woeid ="23424756"';
		$query = 'SELECT * FROM geo.placemaker WHERE documentContent = "They followed him to deepest Africa and found him there, in Timbuktu" AND documentType="text/plain"';

		echo '<pre>';

		print_r( $this->yql( $query ) );
		print_r( $this->errors );

		die;
	}

}//end bGeo_Yahoo class