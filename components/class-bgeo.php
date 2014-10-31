<?php

/*

http://dev.mysql.com/doc/refman/5.0/en/using-a-spatial-index.html
http://dev.mysql.com/doc/refman/5.1/en/geometry-property-functions.html
https://github.com/misterbisson/geoPHP

*/

class bGeo
{
	public $version = 1;
	public $id_base = 'bgeo';
	public $table = FALSE;
	public $plugin_url = FALSE;
	public $geo_taxonomy_name = 'bgeo_tags';

	public $admin = FALSE; // the admin object
	public $tools = FALSE; // the tools object
	public $yboss = FALSE; // the yboss object

	public $options_default = array(
		'taxonomies' => array(
			'category',
			'post_tag',
		),
		'register_geo_taxonomy' => TRUE,
		'yahooapi' => FALSE,
	);

	public function __construct()
	{
		global $wpdb;
		$this->table = $wpdb->prefix . 'bgeo';

		$this->plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );

		// register scripts and styles on init, so they can be used elsewhere
		add_action( 'init', array( $this , 'init' ), 1, 1 );

		// enqueue the registered scripts and styles
		add_action( 'enqueue_scripts', array( $this , 'enqueue_scripts' ), 1, 1 );

		// add our custom geo taxonomy to the list of supported taxonomies
		if( $this->options()->register_geo_taxonomy )
		{
			$this->options()->taxonomies[] = $this->geo_taxonomy_name;
		}

		$this->options()->taxonomies = array_unique( $this->options()->taxonomies );

		if ( is_admin() )
		{
			$this->admin();
			$this->yboss();
		}

	} // END __construct

	public function init()
	{
		// the Leaflet JS library and style
		// see http://leafletjs.com for more info
		wp_register_style( $this->id_base . '-leaflet' , $this->plugin_url . '/external/leaflet/leaflet.css' , array() , $this->version );
		wp_register_script( $this->id_base . '-leaflet', $this->plugin_url . '/external/leaflet/leaflet.js', array( 'jquery' ), $this->version, TRUE );

		// add our custom geo taxonomy to the list of supported taxonomies
		if( $this->options()->register_geo_taxonomy )
		{
			$this->register_taxonomy();
		}

		if ( is_admin() )
		{
			// attempt to load the go-opencalais integration
			// there's a conditional in the following method that checks if the other plugin exists
			$this->go_opencalais();
		}
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

	// a singleton for the yboss object
	public function yboss()
	{
		if ( ! $this->yboss )
		{
			require_once __DIR__ . '/class-bgeo-yboss.php';
			$this->yboss = new bGeo_Yboss();
		}

		return $this->yboss;
	} // END yboss

	// a singleton for the go_opencalais integration object
	public function go_opencalais()
	{
		// sanity check to make sure the go-opencalais plugin is loaded
		if ( ! function_exists( 'go_opencalais' ) )
		{
			return FALSE;
		}

		if ( ! $this->go_opencalais )
		{
			require_once __DIR__ . '/class-bgeo-go-opencalais.php';
			$this->go_opencalais = new bGeo_GO_OpenCalais();
		}

		return $this->go_opencalais;
	} // END go_opencalais

	// get options
	public function options()
	{
		if ( ! isset( $this->options ) )
		{
			$this->options = (object) apply_filters( 
				'go_config',
				$this->options_default,
				$this->id_base
			);
		}

		return $this->options;
	} // END options

	// get a new geometry object
	// see https://github.com/phayes/geoPHP/wiki/API-Reference for docs on geoPHP
	public function new_geometry( $input, $adapter )
	{
		if ( ! class_exists( 'geoPHP' ) )
		{
			require_once __DIR__ . '/external/geoPHP/geoPHP.inc';
			geoPHP::geosInstalled( FALSE ); // prevents a fatal in some cases; @TODO: why?
		}

		return geoPHP::load( $input, $adapter );
	} // END new_geometry

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
			'SELECT AsText(point) AS point, AsText(bounds) AS bounds, area FROM ' . bgeo()->table . ' WHERE term_taxonomy_id = %d',
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
		$point = $this->new_geometry( $geo->point, 'wkt' );
		if ( ! is_object( $point ))
		{
			return FALSE;
		}

		$geo->point_lat = $point->getY();
		$geo->point_lon = $point->getX();
		$geo->point = '{"type":"Feature","geometry":' . $point->out('json') . '}';

		// convert the WKT string into geoJSON
		$bounds = $this->new_geometry( $geo->bounds, 'wkt' );
		if ( ! is_object( $bounds ))
		{
			return FALSE;
		}

		// get the viewport bounds as northwest and southeast corner points of the envelope
		$envelope = $bounds->envelope();
		$geo->bounds_ne = array(
			'lat' => $envelope->components[0]->components[1]->y(),
			'lon' => $envelope->components[0]->components[1]->x(),
		);

		$geo->bounds_se = array(
			'lat' => $envelope->components[0]->components[0]->y(),
			'lon' => $envelope->components[0]->components[0]->x(),
		);

		$geo->bounds = '{"type":"Feature","geometry":' . $bounds->out('json') . '}';

/*
echo '<pre>';
print_r( $geo );
*/
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

/*
echo '<pre>';

echo '<h2>Geo Values</h2>';
print_r( $geo );
*/
		/*
		* Input values can include any or all of a point and boundary.
		* If we have one, but not the other, we generate it.
		* If we have none, we go home.
		*/

		// detect if we have a point, but no bounds
		// insert a point object as the bounds so we can carry on
		if (
			! (
				empty( $geo['point_lat'] ) ||
				empty( $geo['point_lon'] )
			) &&
			! (
				isset( $geo['bounds'] ) &&
				! empty( $geo['bounds'] ) &&
				( $test = json_decode( $geo['bounds'] ) ) &&
				is_object( $test )
			)
		)
		{
			$geo['bounds'] = '{"type":"Point","coordinates":[' . floatval( $geo['point_lon'] ) . ',' . floatval( $geo['point_lat'] ) . ']}';
		}

		// check if we have bounds and if it appears to be json
		if (
			! empty( $geo['bounds'] ) &&
			( $test = json_decode( $geo['bounds'] ) ) &&
			is_object( $test )
		)
		{
			// try to get a geo object for it
			$bounds = $this->new_geometry( $geo['bounds'], 'json' );

			if( ! is_object( $bounds ) )
			{
				$bounds = FALSE;
				continue;
			}

			// simplify the bounding geometry to an envelope
			// this may be an over simplification that gets pealed back later
			$envelope = $bounds->envelope();
			$geo['bounds'] = $envelope->asText();

			// get the area of the envelope
			$geo['area'] = (int) ( $envelope->area() * 100 );

			// if the point isn't set, generate one
			if ( empty( $geo['point_lat'] ) || empty( $geo['point_lon'] ) )
			{
				$point = $envelope->getCentroid();
				$geo['point_lat'] = $point->getY();
				$geo['point_lon'] = $point->getX();
			}
		}

		// validate that we have both point and bounds values
		if ( empty( $geo['point_lat'] ) || empty( $geo['point_lon'] ) || empty( $geo['bounds'] ))
		{
			return FALSE;
		}

		// sanitize the lat and lon
		// @TODO: do some range checking here
		$geo['point_lat'] = floatval( $geo['point_lat'] );
		$geo['point_lon'] = floatval( $geo['point_lon'] );

		// generate the insert query
		global $wpdb;
		$sql = $wpdb->prepare(
			'INSERT INTO ' . bgeo()->table . '
			(
				term_taxonomy_id,
				point,
				bounds,
				area
			)
			VALUES(
				%1$d,
				POINT( %2$f, %3$f ),
				GeomFromText( "%4$s" ),
				%5$d,
				%6$s
			)
			ON DUPLICATE KEY UPDATE point = VALUES( point ), bounds = VALUES( bounds ), area = VALUES( area )',
			$term->term_taxonomy_id,
			floatval( $geo['point_lon'] ),
			floatval( $geo['point_lat'] ),
			$geo['bounds'],
			$geo['area'],
			'0'
		);

		// execute the query
		$wpdb->query( $sql );

/*
echo '<pre>';

echo '<h2>Geo Values</h2>';
print_r( $geo );

echo '<h2>SQL</h2>';
echo $sql;
die;

echo '<h2>WPDB</h2>';
print_r( $wpdb );
*/
	}//end update_geo

	// delete a geo record
	public function delete_geo( $term_id, $taxonomy, $deleted_term = FALSE )
	{
		// This method may be called in response to the delete_term hook,
		// in which case WP has already deleted the term.
		//
		// Or, this method might be called elsewhere
		// to delete the geo info from an existing term.

		// try to figure out the term taxonomy ID
		$term_taxonomy_id = FALSE;
		if ( is_object( $deleted_term ) && isset( $deleted_term->term_taxonomy_id ) )
		{
			$term_taxonomy_id = $deleted_term->term_taxonomy_id;
		}
		elseif ( is_numeric( $deleted_term ))
		{
			$term_taxonomy_id = $deleted_term;
		}
		elseif ( ( $term = get_term( $term_id, $taxonomy ) ) && isset( $term->term_taxonomy_id ) )
		{
			$term_taxonomy_id = $term->term_taxonomy_id;
		}

		// do not continue if we cannot find a TTID
		if ( ! $term_taxonomy_id )
		{
			return FALSE;
		}

		// generate the delete query
		global $wpdb;
		$sql = $wpdb->prepare(
			'DELETE FROM ' . bgeo()->table . ' WHERE term_taxonomy_id = %d',
			$term_taxonomy_id
		);

		// execute the query
		return $wpdb->query( $sql );
	}//end delete_geo

	public function register_taxonomy()
	{

		$post_types = get_post_types( array( 'public' => TRUE , 'publicly_queryable' => TRUE , ) , 'names' , 'or' ); // trivia: 'pages' are public, but not publicly queryable

		register_taxonomy( $this->geo_taxonomy_name, $post_types, array(
			'label' => 'Geographies',
			'labels' => array(
				'singular_name' => 'Geography',
				'menu_name' => 'Geographies',
				'all_items' => 'All geographies',
				'edit_item' => 'Edit geography',
				'view_item' => 'View geography',
				'update_item' => 'Update geography',
				'add_new_item' => 'Add geography',
				'new_item_name' => 'New geography',
				'search_items' => 'Search geographies',
				'popular_items' => 'Popular geographies',
				'separate_items_with_commas' => 'Separate geographies with commas',
				'add_or_remove_items' => 'Add or remove geographies',
				'choose_from_most_used' => 'Choose from most used geographies',
				'not_found' => 'No geographies found',
			),
			// 'hierarchical' => TRUE,
			'show_ui' => TRUE,
			'show_admin_column' => TRUE,
			'query_var' => TRUE,
			'rewrite' => array(
				'slug' => 'geography',
				'with_front' => FALSE,
			),
		) );

	} // END register_taxonomy

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