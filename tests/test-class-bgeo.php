<?php

class bGeo_Test extends WP_UnitTestCase
{
	/**
	 * which tests the constructor, the init action, etc...
	 */
	public function test_singleton()
	{
		$this->assertTrue( is_object( bgeo() ) );
	}//end test_singleton

	/**
	 * create and delete geos
	 * tests bgeo()->update_geo(), bgeo()->get_geo(), bgeo()->delete_geo(), wp_insert_term(), wp_delete_term()
	 */
	public function test_geo_create_and_delete()
	{
		// create our table
		bgeo()->admin()->upgrade();

		// create a wp term
		$term_name = rand( 0, 1000 ) . ' this is a test geo';
		$term_slug = sanitize_title_with_dashes( $term_name );
		if( ! $term = get_term_by( 'slug', $term_slug, bgeo()->geo_taxonomy_name ) )
		{
			$new_term = (object) wp_insert_term( $term_name, bgeo()->geo_taxonomy_name, array( 'slug' => $term_slug ) );
			$term = get_term( $new_term->term_id, bgeo()->geo_taxonomy_name );
		}

		// create a geo attached to that term
		$geo = (object) array(
			'point_lat' => '38.56789',
			'point_lon' => '-121.468849',
			'bounds' => '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[-121.362701,38.43779],[-121.362701,38.6856],[-121.560509,38.6856],[-121.560509,38.43779],[-121.362701,38.43779]]]}}',
		);
		$new_geo = bgeo()->update_geo( $term->term_id, $term->taxonomy, $geo );
		$this->assertTrue( is_object( $new_geo ) );

		$this->assertTrue( isset( $new_geo->term_taxonomy_id ) );

		// it's not an error, right?
		$this->assertFalse( is_wp_error( $new_geo ) );

		// try getting the geo we just created
		$got_geo = bgeo()->get_geo( $term->term_id, $term->taxonomy );
		$this->assertTrue( is_object( $new_geo ) );

		$this->assertTrue( isset( $new_geo->term_taxonomy_id ) );

		// it's not an error, right?
		$this->assertFalse( is_wp_error( $new_geo ) );

		// they match, right?
		$this->assertTrue( $got_geo == $new_geo );

		// what happens when we delete the term?
		wp_delete_term( $term->term_id, $term->taxonomy );
		$deleted_term = get_term( $new_geo->term_id, $new_geo->taxonomy );
		$this->assertTrue( is_null( $deleted_term ) );

		// confirm it's really deleted from the geo table
		global $wpdb;
		$geo_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . bgeo()->table . " WHERE term_taxonomy_id = %d LIMIT 1", $term->term_taxonomy_id ), OBJECT );
		$this->assertTrue( is_null( $geo_row ) );
	}// end test_geo_create_and_delete

	/**
	 * create and get terms by woeid
	 * tests bgeo()->new_geo_by_woeid(), bgeo()->get_geo_by_api_id(), and bgeo()->get_geo_by_ttid()
	 */
	public function test_terms_by_woeid()
	{
		// create our table
		bgeo()->admin()->upgrade();

		// attempt to create a geo with a bogus address
		$this->assertTrue( is_wp_error( bgeo()->new_geo_by_yaddr( (object) array() ) ) );

		// create a geo with a known good woeid
		$new_geo = bgeo()->new_geo_by_woeid( 23512019 );

		// did we get an object back?
		$this->assertTrue( is_object( $new_geo ) );

		// do we have some expected values in that object?
		$this->assertTrue( isset( $new_geo->term_id, $new_geo->api_id ) );

		// attempt to create a duplicate geo
		$dupe_geo = bgeo()->new_geo_by_woeid( 23512019 );
		$this->assertTrue( $new_geo == $dupe_geo );

		// get the term we created
		$previous_term = bgeo()->get_geo_by_api_id( 'woeid', 23512019 );
		$this->assertTrue( is_object( $previous_term ) );

		// it's not an error, right?
		$this->assertFalse( is_wp_error( $previous_term ) );

		// we've got an expected value inside it, right?
		$this->assertTrue( isset( $previous_term->term_taxonomy_id ) );

	}//end test_terms_by_woeid

	/**
	 * create and get terms by yaddr
	 * tests bgeo()->new_geo_by_yaddr(), bgeo()->get_geo_by_api_id(), and bgeo()->get_geo_by_ttid()
	 */
	public function test_terms_by_yaddr()
	{
		$yaddr = (object) array(
			'quality' => 85,
			'addressMatchType' => 'POINT_ADDRESS',
			'latitude' => 37.798,
			'longitude' => -122.40567,
			'offsetlat' => 37.798191,
			'offsetlon' => -122.405693,
			'radius' => 400,
			'name' => NULL,
			'line1' => '504 Broadway',
			'line2' => 'San Francisco, CA 94133-4507',
			'line3' => NULL,
			'line4' => 'United States',
			'house' => '504',
			'street' => 'Broadway',
			'xstreet' => NULL,
			'unittype' => NULL,
			'unit' => NULL,
			'postal' => '94133-4507',
			'neighborhood' => 'Telegraph Hill',
			'city' => 'San Francisco',
			'county' => 'San Francisco County',
			'state' => 'California',
			'country' => 'United States',
			'countrycode' => 'US',
			'statecode' => 'CA',
			'countycode' => NULL,
			'uzip' => '94133',
			'hash' => '30110758956D4BAF',
			'woeid' => 12797183,
			'woetype' => 11
		);

		// create our table
		bgeo()->admin()->upgrade();

		// attempt to create a geo with a bogus woeid
		$this->assertTrue( is_wp_error( bgeo()->new_geo_by_woeid( 'asdf' ) ) );

		// create a geo with a known good address
		$new_geo = bgeo()->new_geo_by_yaddr( $yaddr );

		// did we get an object back?
		$this->assertTrue( is_object( $new_geo ) );

		// do we have some expected values in that object?
		$this->assertTrue( isset( $new_geo->term_id, $new_geo->api_id ) );

		// attempt to create a duplicate geo
		$dupe_geo = bgeo()->new_geo_by_yaddr( $yaddr );
		$this->assertTrue( $new_geo == $dupe_geo );

		// get the term we created
		$previous_term = bgeo()->get_geo_by_api_id( 'yaddr', '30110758956d4baf' );
		$this->assertTrue( is_object( $previous_term ) );

		// it's not an error, right?
		$this->assertFalse( is_wp_error( $previous_term ) );

		// we've got an expected value inside it, right?
		$this->assertTrue( isset( $previous_term->term_taxonomy_id ) );

	}//end test_terms_by_yaddr


}//end class
