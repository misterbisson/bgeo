<?php

/*

http://dev.mysql.com/doc/refman/5.0/en/using-a-spatial-index.html
http://dev.mysql.com/doc/refman/5.1/en/geometry-property-functions.html
https://github.com/misterbisson/geoPHP

*/

class bGeo
{
	public $version = 3;
	public $id_base = 'bgeo';
	public $table = FALSE;
	public $plugin_url = FALSE;
	public $post_types = NULL;
	public $geo_taxonomy_name = 'bgeo_tags';

	public $admin = FALSE; // the admin object
	public $tools = FALSE; // the tools object
	public $yahoo = FALSE; // the yahoo object

	public $apis = array(
		'woeid' => 'Yahoo! Where on Earth ID',
		'yaddr' => 'Yahoo! address lookup',
		'4sqrv' => 'Foursquare venue',
	);

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
			$this->yahoo();
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

		add_action( 'delete_term', array( $this , 'delete_term' ), 5, 4 );
	}

	// a singleton for the admin object
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-bgeo-admin.php';
			$this->admin = new bGeo_Admin( $this );
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

	// a singleton for the yahoo object
	public function yahoo()
	{
		if ( ! $this->yahoo )
		{
			require_once __DIR__ . '/class-bgeo-yahoo.php';
			$this->yahoo = new bGeo_Yahoo();
		}

		return $this->yahoo;
	} // END yahoo

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
	public function get_object_geos( $post_id )
	{
		$terms = wp_get_object_terms( $post_id, $this->geo_taxonomy_name, array( 'fields' => 'ids' ) );
		if ( ! is_array( $terms ) )
		{
			return FALSE;
		}

		$geos = array();
		foreach ( $terms as $term )
		{
			if( ! $geo = $this->get_geo( $term, $this->geo_taxonomy_name ) )
			{
				continue;
			}

			$geos[ $geo->term_taxonomy_id ] = $geo;
		}

		return $geos;
	}

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
			'SELECT
				AsText(point) AS point,
				AsText(bounds) AS bounds,
				area,
				api,
				api_id,
				api_raw,
				belongtos
			FROM ' . $this->table . '
			WHERE term_taxonomy_id = %d',
			$term->term_taxonomy_id
		);

		$geo = $wpdb->get_row( $sql );

		// sanity check
		if ( empty( $geo ) || is_wp_error( $geo ) )
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
		$geo->point = '{"type":"Feature","geometry":' . $point->out( 'json' ) . '}';

		// convert the WKT string into geoJSON
		$bounds = $this->new_geometry( $geo->bounds, 'wkt' );
		if ( ! is_object( $bounds ) )
		{
			return FALSE;
		}

		// get the viewport bounds from the corner points of the envelope
		$envelope = $bounds->envelope();

		$geo->bounds_se = array(
			'lat' => $envelope->components[0]->components[0]->y(),
			'lon' => $envelope->components[0]->components[0]->x(),
		);

		$geo->bounds_ne = array(
			'lat' => $envelope->components[0]->components[1]->y(),
			'lon' => $envelope->components[0]->components[1]->x(),
		);

		$geo->bounds_nw = array(
			'lat' => $envelope->components[0]->components[2]->y(),
			'lon' => $envelope->components[0]->components[2]->x(),
		);

		$geo->bounds_sw = array(
			'lat' => $envelope->components[0]->components[3]->y(),
			'lon' => $envelope->components[0]->components[3]->x(),
		);

		$geo->bounds = '{"type":"Feature","geometry":' . $bounds->out( 'json' ) . '}';

		// unserialize the woe objects/arrays
		$geo->belongtos = maybe_unserialize( $geo->belongtos );
		$geo->api_raw = maybe_unserialize( $geo->api_raw );

/*
echo '<pre>';
print_r( $geo );
*/

		// merge this with the term object and return
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

		if ( ! is_object( $geo ) )
		{
			$geo = (object) $geo;
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
				empty( $geo->point_lat ) ||
				empty( $geo->point_lon )
			) &&
			! (
				isset( $geo->bounds ) &&
				! empty( $geo->bounds ) &&
				( $test = json_decode( $geo->bounds ) ) &&
				is_object( $test )
			)
		)
		{
			$geo->bounds = '{"type":"Point","coordinates":[' . floatval( $geo->point_lon ) . ',' . floatval( $geo->point_lat ) . ']}';
		}

		// check if we have bounds and if it appears to be json
		if (
			! empty( $geo->bounds ) &&
			( $test = json_decode( $geo->bounds ) ) &&
			is_object( $test )
		)
		{
			// try to get a geo object for it
			$bounds = $this->new_geometry( $geo->bounds, 'json' );

			if( ! is_object( $bounds ) )
			{
				$bounds = FALSE;
				continue;
			}

			// simplify the bounding geometry to an envelope
			// this may be an over simplification that gets pealed back later
			// @TODO: remove this simplification
			$envelope = $bounds->envelope();
			$geo->bounds = $envelope->asText();

			// get the area of the envelope
			$geo->area = (int) ( $envelope->area() * 10000 );

			// if the point isn't set, generate one
			if ( empty( $geo->point_lat ) || empty( $geo->point_lon ) )
			{
				$point = $envelope->getCentroid();
				$geo->point_lat = $point->getY();
				$geo->point_lon = $point->getX();
			}
		}

		// validate that we have both point and bounds values
		if ( ! isset( $geo->point_lat, $geo->point_lon, $geo->bounds ))
		{
			return FALSE;
		}

		// sanitize the lat and lon
		// @TODO: do some range checking here
		$geo->point_lat = floatval( $geo->point_lat );
		$geo->point_lon = floatval( $geo->point_lon );

		// set defaults for the WOE columns
		$geo = (object) array_merge(
			array(
				'api' => NULL,
				'api_id' => NULL,
				'api_raw' => NULL,
				'belongtos' => NULL,
			),
			(array) $geo
		);

		// generate the insert query
		global $wpdb;
		$sql = $wpdb->prepare(
			'INSERT INTO ' . $this->table . '
			(
				term_taxonomy_id,
				point,
				bounds,
				area,
				api,
				api_id,
				api_raw,
				belongtos
			)
			VALUES(
				%1$d,
				POINT( %2$f, %3$f ),
				GeomFromText( "%4$s" ),
				"%5$s",
				"%6$s",
				"%7$s",
				"%8$s",
				"%9$s"
			)
			ON DUPLICATE KEY UPDATE
				point = VALUES( point ),
				bounds = VALUES( bounds ),
				area = VALUES( area ),
				api = VALUES( api ),
				api_id = VALUES( api_id ),
				api_raw = VALUES( api_raw ),
				belongtos = VALUES( belongtos )
			',
			$term->term_taxonomy_id,
			floatval( $geo->point_lon ),
			floatval( $geo->point_lat ),
			$geo->bounds,
			$geo->area,
			sanitize_title_with_dashes( $geo->api ),
			sanitize_title_with_dashes( $geo->api_id ),
			maybe_serialize( json_decode( sanitize_text_field( json_encode( $geo->api_raw ) ) ) ), // see http://kitchen.gigaom.com/2014/06/30/quickly-sanitizing-api-feedback/ for how this sanitizes the data without breaking it
			maybe_serialize( $geo->belongtos )
		);

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

		// execute the query and return the geo object
		if ( FALSE === $wpdb->query( $sql ) )
		{
			$error = new WP_Error( 'update_failed', 'Failed to insert or update database row: ' . $wpdb->last_error );
			return $error;
		}

		return $this->get_geo( $term_id, $taxonomy );

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
			'DELETE FROM ' . $this->table . ' WHERE term_taxonomy_id = %d',
			$term_taxonomy_id
		);

		// execute the query
		return $wpdb->query( $sql );
	}//end delete_geo

	public function delete_term( $term_id, $tt_id, $taxonomy, $deleted_term )
	{
		// delete it
		$this->delete_geo( $term_id, $taxonomy, $deleted_term );
	}

	// see get_term_by_ttid() in https://github.com/misterbisson/scriblio-authority/blob/master/components/class-authority-posttype.php#L893
	// for my thoughts on why this is necessary
	public function get_geo_by_ttid( $tt_id )
	{
		global $wpdb;
		$term_id_and_tax = $wpdb->get_row( $wpdb->prepare( "SELECT term_id, taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d LIMIT 1", $tt_id ), OBJECT );
		if( ! $term_id_and_tax )
		{
			$error = new WP_Error( 'invalid_ttid', 'Invalid term taxonomy ID' );
			return $error;
		}
		return $this->get_geo( (int) $term_id_and_tax->term_id, $term_id_and_tax->taxonomy );
	}

	public function get_geo_by_api_id( $api, $api_id )
	{

		// is this a valid API key?
		if ( ! isset( $api, $api_id ) )
		{
			$error = new WP_Error( 'invalid_api_id', 'API and API ID key are both required' );
			return $error;
		}


		// is this a valid API key?
		if ( ! isset( $this->apis[ $api ] ) )
		{
			$error = new WP_Error( 'invalid_api_id', 'Invalid API' );
			return $error;
		}

		global $wpdb;
		$tt_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id
			FROM $this->table
			WHERE 1 = 1
				AND api = %s
				AND api_id = %s
			LIMIT 1", $api, $api_id ) );

		if( ! $tt_id )
		{
			$error = new WP_Error( 'invalid_api_id', 'Invalid or unknown API ID' );
			return $error;
		}
		return $this->get_geo_by_ttid( $tt_id );
	}

	public function new_geo_by_woeid( $woeid )
	{

		// sanity check the woeid
		if ( ! is_numeric( $woeid ) )
		{
			$error = new WP_Error( 'invalid_woeid', 'Invalid WOEID' );
			return $error;
		}

		// check for an existing geo object for this woeid
		$existing = $this->get_geo_by_api_id( 'woeid', $woeid );
		if ( ! is_wp_error( $existing ) )
		{
			return $existing;
		}

		// get all details for this WOEID
		$query = 'SELECT * FROM geo.places WHERE woeid IN (SELECT woeid FROM geo.places WHERE woeid IN ('. $woeid .') )';
		$api_raw = bgeo()->yahoo()->yql( $query );

		// sanity check the response
		if ( ! isset( $api_raw->place ) )
		{
			$error = new WP_Error( 'invalid_woeid', 'API didn\'t return a location, or returned error when looking up WOEID: '. implode( "\n", bgeo()->yahoo()->errors ) );
			return $error;
		}

		$geo = (object) array();
		$geo->api = 'woeid';
		$geo->api_raw = $api_raw->place;
		$geo->api_id = absint( $api_raw->place->woeid );
		$geo->belongtos = $this->get_belongtos( 'woeid', $woeid );

		// whatsoever shall we name this geo?
		$term_name = wp_kses( $geo->api_raw->name, array() );

		// the term description
		$description_parts = array();
		if (
			! empty( $geo->api_raw->placeTypeName->content )
		)
		{
			$description_parts[] = strtolower( $geo->api_raw->placeTypeName->content );
		}

		// if admin2 is present and not the same as the geo name, make that part of the description
		if (
			! empty( $geo->api_raw->admin2->content ) &&
			$geo->api_raw->name != $geo->api_raw->admin2->content
		)
		{
			$description_parts[] = $geo->api_raw->admin2->content;
		}

		// if admin1 is present and not the same as the geo name or admin2, make that part of the description
		if (
			! empty( $geo->api_raw->admin1->content ) &&
			$geo->api_raw->admin2->content != $geo->api_raw->admin1->content &&
			$geo->api_raw->name != $geo->api_raw->admin1->content
		)
		{
			$description_parts[] = $geo->api_raw->admin1->content;
		}

		// if the country name is present and not the same as the geo name, make that part of the description
		if (
			! empty( $geo->api_raw->country->content ) &&
			$geo->api_raw->name != $geo->api_raw->country->content
		)
		{
			$description_parts[] = $geo->api_raw->country->content;
		}

		// get or create a term for this geo
		$term_slug = (int) $geo->api_raw->woeid . '-' . sanitize_title_with_dashes( str_replace( array( '/', '_' ), ' ', $term_name ) );
		if( ! $term = get_term_by( 'slug', $term_slug, $this->geo_taxonomy_name ) )
		{
			$new_term = (object) wp_insert_term( $term_name, $this->geo_taxonomy_name, array( 'slug' => $term_slug, 'description' => implode( ', ', $description_parts ) ) );
			$term = get_term( $new_term->term_id, $this->geo_taxonomy_name );
		}

		// did we get a term?
		if ( ! isset( $term->term_taxonomy_id ) )
		{
			$error = new WP_Error( 'no_term', 'Either couldn\'t find or couldn\'t create a term for this WOEID' );
			return $error;
		}

		// get the centroid for this geo
		$point = $this->new_geometry( '{ "type": "Point", "coordinates": [' . $geo->api_raw->centroid->longitude . ', ' . $geo->api_raw->centroid->latitude . '] }', 'json' );
		$geo->point_lat = $point->getY();
		$geo->point_lon = $point->getX();

		// get the bounding box for this geo
		$bounds = $this->new_geometry( '{ "type": "LineString", "coordinates": [ [' . $geo->api_raw->boundingBox->southWest->longitude . ', ' . $geo->api_raw->boundingBox->southWest->latitude . '], [' . $geo->api_raw->boundingBox->northEast->longitude . ', ' . $geo->api_raw->boundingBox->northEast->latitude . '] ]}', 'json' );
		$geo->bounds = $bounds->envelope()->out( 'json' );

		$this->update_geo( $term->term_id, $term->taxonomy, $geo );

		return $this->get_geo( $term->term_id, $term->taxonomy );
	}

	public function new_geo_by_yaddr( $yaddr_object )
	{

		// sanity check the yaddr
		if ( ! is_object( $yaddr_object ) )
		{
			$error = new WP_Error( 'invalid_yaddr', 'Invalid Yahoo address object' );
			return $error;
		}

		// the WOEID is required
		if ( empty( $yaddr_object->woeid ) )
		{
			$error = new WP_Error( 'invalid_yaddr', 'No WOEID present in the Yahoo address object' );
			return $error;
		}

		// if the address match isn't good enough to get a hash, then it's only as specific as the WOEID
		if ( empty( $yaddr_object->hash ) )
		{
			return $this->new_geo_by_woeid( $yaddr_object->woeid );
		}

		// check for an existing geo object for this hash
		$existing = $this->get_geo_by_api_id( 'yaddr', $yaddr_object->hash );
		if ( ! is_wp_error( $existing ) )
		{
			return $existing;
		}

		$geo = (object) array();
		$geo->api = 'yaddr';
		$geo->api_raw = $yaddr_object;
		$geo->api_id = $geo->api_raw->hash;
		$geo->belongtos = $this->get_belongtos( 'woeid', $geo->api_raw->woeid );

		// whatsoever shall we name this geo?
		$name_parts = array_intersect_key( (array) $geo->api_raw, array(
			'line1' => TRUE,
			'line2' => TRUE,
			'line3' => TRUE,
			'line4' => TRUE,
		) );
		$term_name = wp_kses( implode( ' ', $name_parts ), array() );

		// get or create a term for this geo
		$term_slug = (int) $geo->api_raw->woeid . '-' . sanitize_title_with_dashes( str_replace( array( '/', '_' ), ' ', $term_name ) );
		if( ! $term = get_term_by( 'slug', $term_slug, $this->geo_taxonomy_name ) )
		{
			$new_term = (object) wp_insert_term( $term_name, $this->geo_taxonomy_name, array( 'slug' => $term_slug, 'description' => 'address, ' . $geo->api_raw->country ) );
			$term = get_term( $new_term->term_id, $this->geo_taxonomy_name );
		}

		// did we get a term?
		if ( ! isset( $term->term_taxonomy_id ) )
		{
			$error = new WP_Error( 'no_term', 'Either couldn\'t find or couldn\'t create a term for this Yahoo address object' );
			return $error;
		}

		// get the centroid for this geo
		$point = $this->new_geometry( '{ "type": "Point", "coordinates": [' . $geo->api_raw->longitude . ', ' . $geo->api_raw->offsetlat . '] }', 'json' );
		$geo->point_lat = $point->getY();
		$geo->point_lon = $point->getX();

		// get the bounding box for this geo
		$bounds = $this->new_geometry( '{ "type": "LineString", "coordinates": [ [' . ( $geo->api_raw->offsetlon + 0.0001 ) . ', ' . ( $geo->api_raw->offsetlat + 0.0001 ) . '], [' . ( $geo->api_raw->offsetlon - 0.0001 ) . ', ' . ( $geo->api_raw->offsetlat - 0.0001 ) . '] ]}', 'json' );
		$geo->bounds = $bounds->envelope()->out( 'json' );

		$this->update_geo( $term->term_id, $term->taxonomy, $geo );
		return $this->get_geo( $term->term_id, $term->taxonomy );
	}

	public function get_belongtos( $api, $api_id )
	{

		// check for an existing geo object for this item
		$existing = $this->get_geo_by_api_id( $api, $api_id );
		if ( ! is_wp_error( $existing ) )
		{
			return $existing->belongtos;
		}

		// we can only look up belongtos by WOEID
		if ( 'woeid' != $api )
		{
			return array();
		}

		// get the belongto woeids
		$query = 'SELECT woeid FROM geo.places.belongtos WHERE member_woeid IN (SELECT woeid FROM geo.places WHERE woeid IN ('. $api_id .') )';
		$api_raw = bgeo()->yahoo()->yql( $query );

		// did we get anything?
		if ( ! isset( $api_raw->place ) )
		{
			return array();
		}

		// extract any results
		$belongtos = array();
		if ( ! is_array( $api_raw->place ) )
		{
			$api_raw->place = array( $belongtos->place );
		}

		foreach ( $api_raw->place as $temp )
		{
			$belongtos[] = (object) array(
				'api' => 'woeid',
				'api_id' => $temp->woeid,
			);
		}

		return $belongtos;
	}

	public function register_taxonomy()
	{

		$this->post_types = get_post_types( array( 'public' => TRUE , 'publicly_queryable' => TRUE , ) , 'names' , 'or' ); // trivia: 'pages' are public, but not publicly queryable

		register_taxonomy( $this->geo_taxonomy_name, $this->post_types, array(
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