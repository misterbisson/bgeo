<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bGeo_Admin_Postmeta
{
	private $bgeo = NULL;
	private $id_base = 'bgeo_meta_canary'; // 'canary' because it's purpose is to watch for changes

	/**
	 * constructor
	 */
	public function __construct( $bgeo )
	{
		$this->bgeo = $bgeo;
		add_action( 'init', array( $this, 'init' ) );
	}//end __construct

	/**
	 * Start things up!
	 */
	public function init()
	{
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
	}//end init

	// @TODO: this method will need to be refactored based on what we do in update_post_meta()
	public function get_post_meta( $post_id )
	{
		if ( ! $meta = (object) get_post_meta( $post_id, $this->id_base, TRUE ) )
		{
			return (object) array(
				'hash' => NULL,
				'geo' => NULL,
			);
		} // END if

		return $meta;
	} // END get_post_meta

	// @TODO: this method is incomplete
	public function update_post_meta( $post_id, $meta )
	{
		$term_ids = $this->bgeo->admin->posts()->get_term_ids_from_geo( $meta->geo );
		wp_set_object_terms( $post_id, $term_ids, $meta->geo->taxonomy, TRUE );
		update_post_meta( $post_id, $this->id_base, $meta );
	} // END update_post_meta

	public function locationlookup( $text )
	{
		return $this->bgeo->admin()->posts()->locationlookup( $text, TRUE );
	}// END locationlookup

	// see wp docs at http://codex.wordpress.org/Geodata for background
	public function get_core_geo_meta( $post_id )
	{
		$meta = (array) get_post_meta( $post_id );
		$meta = array_intersect_key( $meta, array(
			'geo_latitude' => TRUE,
			'geo_longitude' => TRUE,
			'geo_address' => TRUE,
		) );

		if ( empty( $meta ) )
		{
			return array();
		}

		// extract the string values
		foreach ( $meta as $k => $v )
		{
			if ( ! is_array( $v ) )
			{
				continue;
			}

			$v = current( $v );
			if ( ! is_string( $v ) )
			{
				continue;
			}

			$meta[ $k ] = $v;
		}

		return $meta;
	}// END get_core_geo_meta

	public function update_location_from_core_geo_meta( $post_id )
	{
		// check for core geo meta
		$core_meta = $this->get_core_geo_meta( $post_id );
		if ( empty( $core_meta ) )
		{
			return FALSE;
		}

		// check for our geo meta
		$my_meta = $this->get_post_meta( $post_id );

		// have we seen this before?
		$core_hash = md5( serialize( $core_meta ) );
		if (
			$my_meta->hash == $core_hash &&
			( $geo = $this->bgeo->get_geo_by( 'slug', $my_meta->geo->slug ) ) &&
			! is_wp_error( $geo )
		)
		{
			return $geo;
		}

		// try to get a reverse geocode using the core meta
		$locations = $this->locationlookup( implode( ', ', $core_meta ) );
		if ( ! is_array( $locations ) )
		{
			return FALSE;
		}

		// extract the first result from the array
		$location = current( $locations );

		// save it to meta so we don't have to make API calls on future saves (unless it changes)
		$this->update_post_meta( $post_id, (object) array(
			'hash' => $core_hash,
			'geo' => $location,			
		) );

		return $location;
	}// END update_location_from_core_geo_meta

	public function save_post( $post_id, $post )
	{
		$this->update_location_from_core_geo_meta( $post_id );
	}// END save_post

}//end bGeo_Admin_Postmeta class