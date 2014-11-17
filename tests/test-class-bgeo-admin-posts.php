<?php

class bGeo_Admin_Posts_Test extends WP_UnitTestCase
{
	/**
	 * which tests the constructor, the init action, etc...
	 */
	public function test_accesor()
	{
		$this->assertTrue( is_object( bgeo()->admin()->posts() ) );
	}//end test_singleton

}//end class
