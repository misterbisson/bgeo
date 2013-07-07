<?php

/*

http://dev.mysql.com/doc/refman/5.0/en/using-a-spatial-index.html
http://dev.mysql.com/doc/refman/5.1/en/geometry-property-functions.html

*/

class bGeo
{
	public $admin = FALSE; // the admin object
	public $tools = FALSE; // the tools object
	public $table = FALSE;
	public $plugin_url = FALSE;
	public $version = 1;
	public $id_base = 'bgeo';

	public function __construct()
	{
		global $wpdb;
		$this->table = $wpdb->prefix . 'bgeo';

		$this->plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );

		// register scripts and styles on init, so they can be used elsewhere
		add_action( 'init', array( $this , 'init' ), 1, 1 );

		// enqueue the registered scripts and styles
		add_action( 'enqueue_scripts', array( $this , 'enqueue_scripts' ), 1, 1 );

		if ( is_admin() )
		{
			$this->admin();
		}

	} // END __construct

	public function init()
	{
		// the Leaflet JS library and style
		// see http://leafletjs.com for more info
		wp_register_style( $this->id_base . '-leaflet' , $this->plugin_url . '/external/leaflet/leaflet.css' , array() , $this->version );
		wp_register_script( $this->id_base . '-leaflet', $this->plugin_url . '/external/leaflet/leaflet.js', array( 'jquery' ), $this->version, TRUE );
	}

	// a singleton for the admin object
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once dirname( __FILE__ ) . '/class-bgeo-admin.php';
			$this->admin = new bGeo_Admin();
		}

		return $this->admin;
	} // END admin

	// a singleton for the tools object
	public function tools()
	{
		if ( ! $this->tools )
		{
			require_once dirname( __FILE__ ) . '/class-bgeo-tools.php';
			$this->tools = new bGeo_Tools();
		}

		return $this->tools;
	} // END tools

	// get a geo record
	public function get_geo( $term_id, $taxonomy )
	{
		$term = get_term( $term_id, $taxonomy );

		if ( ! isset( $term->term_taxonomy_id ) )
		{
			return FALSE;
		}

		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . bgeo()->table . ' WHERE term_taxonomy_id = %d',
			$term->term_taxonomy_id
		);

var_dump( $sql );

	}//end get_geo

	// update a geo record
	public function update_geo( $term_id, $taxonomy, $geo )
	{
		$old = $this->get_geo( $term_id, $taxonomy );
		$term = get_term( $term_id, $taxonomy );

		if ( ! isset( $term->term_taxonomy_id ) )
		{
			return FALSE;
		}

		// validate that we have both lat and lon values
		// @TODO: a smart person would also range check these values
		if ( ! isset( $geo['coordinates-lat'] , $geo['coordinates-lon'] ))
		{
			return FALSE;
		}

		// sanitize the lat and lon
		if ( isset( $geo['coordinates-lat'] ) )
		{
			$geo['coordinates-lat'] = floatval( $geo['coordinates-lat'] );
		}

		if ( isset( $geo['coordinates-lon'] ) )
		{
			$geo['coordinates-lon'] = floatval( $geo['coordinates-lon'] );
		}


echo '<pre>';
print_r( $term );

		global $wpdb;

		$sql = $wpdb->prepare(
			'INSERT INTO ' . bgeo()->table . ' 
			(
				term_taxonomy_id,
				point,
				ring,
				area
			)
			VALUES( 
				%1$d,
				POINT( %2$f, %3$f ),
				ExteriorRing ( Envelope( POINT( %2$f, %3$f ) ) ),
				Area( Envelope( POINT( %2$f, %3$f ) ) )
			)',
			$term->term_taxonomy_id,
			floatval( $geo['coordinates-lat'] ),
			floatval( $geo['coordinates-lon'] )
		);

// INSERT INTO wp_1_bgeo (term_taxonomy_id, point, poly, area) VALUES( 6862, Point(15,0), Envelope( Point(15,0) ), Area( Envelope( Point(15,0) ) ) );
// INSERT INTO wp_1_bgeo (term_taxonomy_id, point, ring, area) VALUES( 6862, Point(15,0), ExteriorRing ( Envelope( Point(15,0) ) ), Area( Envelope( Point(15,0) ) ) );

var_dump( $term_id, $taxonomy, $geo, $sql );
die;

	}//end update_geo

	// delete a geo record
	public function delete_geo( $term_id, $taxonomy )
	{
var_dump( $term_id, $tt_id, $taxonomy );

	}//end delete_geo

	// enqueue the scripts and styles
	public function enqueue_scripts()
	{
		wp_enqueue_style( $this->id_base . '-leaflet' );
		wp_enqueue_script( $this->id_base . '-leaflet' );
	}//end enqueue_scripts

} // END bGeo class

// Singleton
function bgeo()
{
	global $bgeo;

	if ( ! $bgeo )
	{
		$bgeo = new bGeo();
	}

	return $bgeo;
} // END bgeo