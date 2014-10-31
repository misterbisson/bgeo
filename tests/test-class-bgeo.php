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
}//end class
