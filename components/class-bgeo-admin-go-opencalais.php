<?php
/*
This class includes code to integrate with https://github.com/GigaOM/go-opencalais.
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

	public function __construct( $bgeo )
	{
		$this->bgeo = $bgeo;

		$this->register_taxonomy();

		add_filter( 'bgeo_locationsfromtext', array( $this, 'bgeo_locationsfromtext' ), 2, 3 );
	}

	public function bgeo_locationsfromtext( $locations, $post_id, $text )
	{

		// construct a post object as required for the enrich class
		$post = clone get_post( $post_id );
		$post->post_content = $text;

		// instantiate the enrich class and execute
		$enrich_obj = go_opencalais()->admin()->enrich( $post );
		$enrich_obj->enrich();

		// did we get a result from opencalais?
		if( ! is_array( $enrich_obj->response ) )
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
			}
		}

		return $locations;
	}

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
		return $this->bgeo->get_geo_by( 'slug',  trim( $term->description ) );
	}

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

		return $this->bgeo->get_geo_by( 'slug',  trim( $term->description ) );
	}

	public function sanitize_opencalais_location( $opencalais_location )
	{
		if( 
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
	}

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

}//end bGeo_Admin_GO_OpenCalais class