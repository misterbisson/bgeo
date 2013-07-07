<?php

/*

http://dev.mysql.com/doc/refman/5.0/en/using-a-spatial-index.html
http://dev.mysql.com/doc/refman/5.1/en/geometry-property-functions.html
https://github.com/misterbisson/geoPHP

*/

class bGeo
{
	public $admin = FALSE; // the admin object
	public $tools = FALSE; // the tools object
	public $geophp_loaded = FALSE; // is the geoPHP converter loaded?
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
			require_once __DIR__ . '/class-bgeo-admin.php';
			$this->admin = new bGeo_Admin();
		}

		return $this->admin;
	} // END admin

	// a singleton for the tools object
	public function tools()
	{
		if ( ! $this->tools )
		{
			require_once __DIR__ . '/class-bgeo-tools.php';
			$this->tools = new bGeo_Tools();
		}

		return $this->tools;
	} // END tools

	public function new_geo_php( $input, $adapter )
	{
		if ( ! $this->geophp_loaded )
		{
			require_once __DIR__ . '/external/geoPHP/geoPHP.inc';
			$this->geophp_loaded = TRUE;
		}

		return geoPHP::load( $input, $adapter );
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
			'SELECT AsText(point) AS point, AsText(bounds) AS bounds, area, woeid FROM ' . bgeo()->table . ' WHERE term_taxonomy_id = %d',
			$term->term_taxonomy_id
		);

		$geo = $wpdb->get_row( $sql );

		// sanity check
		if ( is_wp_error( $geo ))
		{
			return FALSE;
		}

		// convert the WKT string into geoJSON, 
		// also extract separate lat and lon values
		$point = $this->new_geo_php( $geo->point, 'wkt' );
		if ( ! is_object( $point ))
		{
			return FALSE;
		}

		$geo->point = $point->out('json');
		$geo->point_lat = $point->getX();
		$geo->point_lon = $point->getY();

		// convert the WKT string into geoJSON
		$bounds = $this->new_geo_php( $geo->bounds, 'wkt' );
		if ( ! is_object( $bounds ))
		{
			return FALSE;
		}

		$geo->bounds = $bounds->out('json');

		return (object) array_merge( (array) $term, (array) $geo );

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

		// check if we have bounds and if it appears to be json
		if ( 
			isset( $geo['bounds'] ) &&
			( $test = json_decode( $geo['bounds'] ) ) &&
			is_object( $test )
		)
		{
			// try to get a geo object for it
			$bounds = $this->new_geo_php( $geo['bounds'], 'json' );

			if( ! is_object( $bounds ))
			{
				$bounds = FALSE;
				continue;
			}

			// if the point isn't set, generate one
			if ( ! isset( $geo['point_lat'] , $geo['point_lon'] ))
			{
				$point = $bounds->getCentroid();
				$geo['point_lat'] = $point->getX();
				$geo['point_lon'] = $point->getY();
			}
		}


echo '<pre>';
echo $geo['bounds'];

var_dump( $bounds->getArea() );
var_dump( $bounds->asText() );
var_dump( bin2hex( $bounds->asBinary() ) );


		// validate that we have both lat and lon values
		// @TODO: a smart person would also range check these values
		if ( ! isset( $geo['point_lat'] , $geo['point_lon'] ))
		{
			return FALSE;
		}

		// sanitize the lat and lon
		if ( isset( $geo['point_lat'] ) )
		{
			$geo['point_lat'] = floatval( $geo['point_lat'] );
		}

		if ( isset( $geo['point_lon'] ) )
		{
			$geo['point_lon'] = floatval( $geo['point_lon'] );
		}





		global $wpdb;

		$sql = $wpdb->prepare(
			'INSERT INTO ' . bgeo()->table . ' 
			(
				term_taxonomy_id,
				point,
				bounds,
				area,
				woeid
			)
			VALUES( 
				%1$d,
				POINT( %2$f, %3$f ),
				ExteriorRing ( Envelope( POINT( %2$f, %3$f ) ) ),
				Area( Envelope( POINT( %2$f, %3$f ) ) ),
				%4$s
			)
			ON DUPLICATE KEY UPDATE point = VALUES( point ), bounds = VALUES( bounds ), area = VALUES( area ), woeid = VALUES( woeid )',
			$term->term_taxonomy_id,
			floatval( $geo['point_lat'] ),
			floatval( $geo['point_lon'] ),
			empty( $bounds ) ?  : bin2hex( $bounds->asBinary() ),
			'0'
		);

		print_r( $wpdb->get_results( $sql ));
		print_r( $wpdb );

// INSERT INTO wp_1_bgeo (term_taxonomy_id, point, poly, area) VALUES( 6862, Point(15,0), Envelope( Point(15,0) ), Area( Envelope( Point(15,0) ) ) );
// INSERT INTO wp_1_bgeo (term_taxonomy_id, point, bounds, area) VALUES( 6862, Point(15,0), ExteriorRing ( Envelope( Point(15,0) ) ), Area( Envelope( Point(15,0) ) ) );


echo '<pre>';

print_r( $term );

/*
var_dump( $sql );

$polygon = $this->new_geo_php( 'POLYGON((1 1,5 1,5 5,1 5,1 1),(2 2,2 3,3 3,3 2,2 2))','wkt' );
var_dump( $polygon->getArea() );
var_dump( $polygon->getCentroid() );
var_dump( $polygon->getX() );
var_dump( $polygon->getY() );
*/


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