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

	public function __construct()
	{
		if ( is_admin() )
		{
			add_action( 'wp_ajax_bgeo-yboss-finder', array( $this, 'finder_ajax' ));
			add_action( 'wp_ajax_bgeo-yboss-spotter', array( $this, 'spotter_ajax' ));
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

	public function do_boss_request( $api, $args )
	{
		echo '<pre>';

		if ( ! isset( $api ) || ! is_string( $api ) )
		{
			return FALSE;
		}

		if ( ! isset( $args ) || ! is_array( $args ) )
		{
			return FALSE;
		}

		// basic request data
		$url = 'http://yboss.yahooapis.com/' . $api;
		$cc_key  = 'secret--';
		$cc_secret = 'secret';

		// construct a request signiture
		if ( ! class_exists( 'OAuthConsumer' ) )
		{
			require_once __DIR__ . '/external/OAuth.php';
		}
		$consumer = new OAuthConsumer( $cc_key, $cc_secret );
		$request = OAuthRequest::from_consumer_and_token( $consumer, NULL, 'GET', $url, $args );
		$request->sign_request( new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL );

		// get the authorization header
		$headers = array( $request->to_header() );

		// do the request
		$raw_result = wp_remote_get(
			sprintf( '%s?%s', $url, OAuthUtil::build_http_query( $args ) ),
			array(
				'headers' => array(
					'Authorization' => str_replace( 'Authorization: ', '', $headers[0] )
				)
			)
		);

		// is the raw response an array?
		if( ! is_array( $raw_result ) )
		{
			return FALSE;
		}

		// does the raw response array contain a code, is that 200?
		if( ! isset( $raw_result['response']['code'] ) || 200 != (int) $raw_result['response']['code'] )
		{
			return FALSE;
		}

		// is the raw response body parseable as json?
		if (
			empty( $raw_result['body'] ) ||
			! ( $test = json_decode( $raw_result['body'] ) ) ||
			! is_object( $test )
		)
		{
			return FALSE;
		}

		return json_decode( $raw_result['body'] );
	}

	public function finder_ajax( $query )
	{

		$query = 'Aberdeen -98.522563831024 45.444191924201';

		echo '<pre>';

		$args = array();
		$args['q'] = $query;

		// set the output to JSON
		$args['flags'] = 'J';

		print_r( $this->do_boss_request( 'geo/placefinder', $args ) );

	}

	public function spotter_ajax()
	{
		// http://developer.yahoo.com/boss/geo/docs/key-concepts.html
		// http://developer.yahoo.com/boss/geo/docs/codeexamples.html#oauth_php
	}

}//end bGeo_Yboss class