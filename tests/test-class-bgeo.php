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
	 * create and get terms by woeid
	 * tests bgeo()->new_geo_by_woeid(), bgeo()->get_geo_by_woeid(), and bgeo()->get_geo_by_ttid()
	 */
	public function test_terms_by_woeid()
	{
		// create our table
		bgeo()->admin()->upgrade();

		// attempt to create a geo with a bogus woeid
		$this->assertTrue( is_wp_error( bgeo()->new_geo_by_woeid( 'asdf' ) ) );

		// create a geo with a known good woeid
		$new_geo = bgeo()->new_geo_by_woeid( 23512019 );

		// did we get an object back?
		$this->assertTrue( is_object( $new_geo ) );

		// do we have some expected values in that object?
		$this->assertTrue( isset( $new_geo->term_id, $new_geo->woeid ) );

		// attempt to create a duplicate geo
		$dupe_geo = bgeo()->new_geo_by_woeid( 23512019 );
		$this->assertTrue( $new_geo == $dupe_geo );

		// get the term we created
		$previous_term = bgeo()->get_geo_by_woeid( 23512019 );
		$this->assertTrue( is_object( $previous_term ) );

		// it's not an error, right?
		$this->assertFalse( is_wp_error( $previous_term ) );

		// we've got an expected value inside it, right?
		$this->assertTrue( isset( $previous_term->term_taxonomy_id ) );

	}//end test_singleton
}//end class
