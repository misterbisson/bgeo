<?php

class bGeo_Admin_Test extends WP_UnitTestCase
{
	/**
	 * which tests the constructor, the init action, etc...
	 */
	public function test_singleton()
	{
		$this->assertTrue( is_object( bgeo()->admin() ) );
	}//end test_singleton

	/**
	 * tests that the version number is set in the database
	 */
	public function test_upgrade()
	{
		bgeo()->admin()->upgrade();
		$options = get_option( bgeo()->id_base );

		$this->assertTrue( bgeo()->version == $options['version'] );
	}//end test_upgrade

	/**
	 * tests that the table is created as expected
	 */
	public function test_create_table()
	{
		global $wpdb;

		bgeo()->admin()->create_table();

		$sql = 'DESCRIBE '. bgeo()->table;
		$table = $wpdb->get_results( $sql );

		$this->assertTrue( is_array( $table ) );
		$this->assertTrue( 'term_taxonomy_id' == $table[0]->Field );
	}//end test_upgrade

}//end class
