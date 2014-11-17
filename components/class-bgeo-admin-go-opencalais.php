<?php
/*
 * This class leverages https://github.com/GigaOM/go-opencalais to improve the relevance of suggested locations.
 * Use requires an OpenCalais key, see http://www.opencalais.com for more information about that service.
 */

class bGeo_Admin_GO_OpenCalais
{

	private $bgeo = NULL;
	public $geo_taxonomy_name = 'bgeo_opencalais_tags'; // intentionally different from the normal bGeo taxonomy
	public $entity_types = array(
		'City'               => 'bgeo_tags',
		'ProvinceOrState'    => 'bgeo_tags',
		'Country'            => 'bgeo_tags',
		'Continent'          => 'bgeo_tags',
		'Region'             => 'bgeo_tags',
	);

	/**
	 * Constructor
	 */
	public function __construct( $bgeo )
	{
		$this->bgeo = $bgeo;

		$this->register_taxonomy();

		add_filter( 'bgeo_locationsfromtext', array( $this, 'bgeo_locationsfromtext' ), 2, 3 );
	}// END __construct

	/**
	 * Filter the geo entities extracted from the text/post content.
	 *
	 * OpenCalais focuses on relevant matches (and includes a relevance rank).
	 * Yahoo!'s location objects are more useful, but the location extraction
	 * includes more, often less relevant results.
	 *
	 * Combining both, converting OpenCalais suggestions to Yahoo geo objects,
	 * and using the OpenCalais ranking, can lead to higher quality suggestions
	 */
	public function bgeo_locationsfromtext( $locations, $post_id, $text )
	{

		// construct a post object as required for the enrich class
		$post = clone get_post( $post_id );
		$post->post_content = $text;

		// instantiate the enrich class and execute
		$enrich_obj = go_opencalais()->admin()->enrich( $post );
		$enrich_obj->enrich();

		// did we get a result from opencalais?
		if ( ! is_array( $enrich_obj->response ) )
		{
			return $locations;
		}

		// extract the raw locations from the API output
		foreach ( $enrich_obj->response as $k => $v )
		{
			if ( ! isset( $v->_type, $v->name, $v->resolutions, $this->entity_types[ $v->_type ] ) )
			{
				continue;
			}

			foreach ( $v->resolutions as $resolution )
			{
				if (
					( ! $location = $this->get_geo( $resolution ) ) ||
					is_wp_error( $location )
				)
				{
					continue;
				}

				$location->relevance = ( $v->relevance + 1 );

				if ( isset( $locations[ $location->term_taxonomy_id ] ) )
				{
					$locations[ $location->term_taxonomy_id ]->relevance += ( $v->relevance + 1 );
					continue;
				}

				$locations[ $location->term_taxonomy_id ] = $location;
			}//end foreach
		}//end foreach

		return $locations;
	}// END bgeo_locationsfromtext

	/**
	 * Returns an existing geo object for a given OpenCalais location record.
	 *
	 * If no existing geo is found, it passes the location on to create_geo(), which creates it.
	 *
	 * returns a geo location
	 */
	public function get_geo( $opencalais_location )
	{
		// sanitize the location
		if ( ! $opencalais_location = $this->sanitize_opencalais_location( $opencalais_location ) )
		{
			return FALSE;
		}

		// lookup the term locally, attempt to create the term if not found
		if ( ! $term = get_term_by( 'slug', $opencalais_location->term->slug, $this->geo_taxonomy_name ) )
		{
			return $this->create_geo( $opencalais_location );
		}

		// the term's description is stuffed with a term taxonomy ID of the proper geo term
		if (  empty( $term->description ) )
		{
			return FALSE;
		}

		// lookup and return the proper geo for this TTID. Or try to, anyway.
		return $this->bgeo->get_geo_by( 'slug', trim( $term->description ) );
	}// END get_geo

	/**
	 * Creates a geo object for a given OpenCalais location record.
	 * OpenCalais locations are stored as terms in a custom taxonomy specifically for this purpose.
	 * This creates a persistent map between OpenCalais term IDs (expressed as linked data URLs) and Yahoo locations.
	 *
	 * term slug is the md5() of the OpenCalais term ID
	 * term name is the fully qualified location named given from OpenCalais
	 * term description is the slug identifying the proper geo term in the normal bGeo taxonomy
	 *
	 * returns a geo location
	 */
	public function create_geo( $opencalais_location )
	{
		// sanitize the location
		if ( ! $opencalais_location = $this->sanitize_opencalais_location( $opencalais_location ) )
		{
			$error = new WP_Error( 'no_term', 'The OpenCalais location appears invalid' );
			return $error;
		}

		// check the Yahoo placefinder API with this term to resolve it into a better geocode
		$location_by_yaddr = $this->bgeo->admin()->posts()->locationlookup( $opencalais_location->term->name );

		if ( ! is_array( $location_by_yaddr ) )
		{
			$error = new WP_Error( 'no_term', 'Couldn\'t resolve OpenCalais location to Yaddr' );
			return $error;
		}

		// @TODO how to handle multiple responses when an exact match is expected?
		$location_by_yaddr = current( $location_by_yaddr );

		if ( ! isset( $location_by_yaddr->slug ) )
		{
			$error = new WP_Error( 'no_term', 'Resolved Yaddr doesn\'t appear valid' );
			return $error;
		}

		$new_term = (object) wp_insert_term(
			$opencalais_location->term->name,
			$this->geo_taxonomy_name,
			array(
				'slug' => $opencalais_location->term->slug,
				'description' => $location_by_yaddr->slug,
			)
		);
		$term = get_term( $new_term->term_id, $this->geo_taxonomy_name );

		// did we get a term?
		if ( ! isset( $term->term_taxonomy_id, $term->description ) )
		{
			$error = new WP_Error( 'no_term', 'Either couldn\'t find or couldn\'t create a term for this WOEID' );
			return $error;
		}

		return $this->bgeo->get_geo_by( 'slug', trim( $term->description ) );
	}// END create_geo

	/**
	 * Validates that the provided location has its data, creates the slug and name for the term
	 */
	public function sanitize_opencalais_location( $opencalais_location )
	{
		if (
			! is_object( $opencalais_location ) ||
			! isset( $opencalais_location->id, $opencalais_location->name )
		)
		{
			return FALSE;
		}

		$opencalais_location->term = (object) array(
			'slug' => md5( $opencalais_location->id ),
			'name' => str_replace( ',', ' - ', $opencalais_location->name ),
		);

		return $opencalais_location;
	}// END sanitize_opencalais_location

	/**
	 * Register our custom taxonomy
	 */
	public function register_taxonomy()
	{
		register_taxonomy( $this->geo_taxonomy_name, $this->bgeo->post_types, array(
			'label' => 'OpenCalais Geographies',
			'labels' => array(
				'singular_name' => 'OpenCalais Geography',
				'menu_name' => 'OpenCalais Geographies',
				'all_items' => 'All OpenCalais geographies',
				'edit_item' => 'Edit OpenCalais geography',
				'view_item' => 'View OpenCalais geography',
				'update_item' => 'Update OpenCalais geography',
				'add_new_item' => 'Add OpenCalais geography',
				'new_item_name' => 'New OpenCalais geography',
				'search_items' => 'Search OpenCalais geographies',
				'popular_items' => 'Popular OpenCalais geographies',
				'separate_items_with_commas' => 'Separate OpenCalais geographies with commas',
				'add_or_remove_items' => 'Add or remove OpenCalais geographies',
				'choose_from_most_used' => 'Choose from most used OpenCalais geographies',
				'not_found' => 'No OpenCalais geographies found',
			),
			'public' => FALSE,
			'show_ui' => FALSE,
			'show_admin_column' => FALSE,
			'query_var' => FALSE,
			'rewrite' => FALSE,
		) );

	}// END register_taxonomy
}//end class