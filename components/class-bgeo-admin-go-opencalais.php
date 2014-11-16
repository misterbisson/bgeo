<?php
/*
This class includes code to integrate with https://github.com/GigaOM/go-opencalais.
*/

class bGeo_Admin_GO_OpenCalais
{

	public $entity_types = array(
		'City'               => 'bgeo_tags',
		'ProvinceOrState'    => 'bgeo_tags',
		'Country'            => 'bgeo_tags',
		'Continent'          => 'bgeo_tags',
		'Region'             => 'bgeo_tags',
	);

	public function __construct()
	{
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

echo '<h2>' . __LINE__ . '</h2>';

//print_r( $enrich_obj->response );

		// did we get a result from opencalais?
		if( ! is_array( $enrich_obj->response ) )
		{
			return $locations;
		}

		// extract the raw locations from the API output
		$opencalais_locations = array();
		foreach ( $enrich_obj->response as $k => $v )
		{
			if ( ! isset( $v->_type, $v->name, $v->resolutions, $this->entity_types[ $v->_type ] ) )
			{
				continue;
			}

			foreach ( $v->resolutions as $resolution )
			{
				$opencalais_locations[] = $resolution;
			}
		}

		// go no further if we have no locations to process
		if( empty( $opencalais_locations ) )
		{
			return $locations;
		}


print_r( $opencalais_locations );


		die;

		print_r( $locations );
		print_r( $post_id );
		print_r( $text );
		die;
	}

}//end bGeo_Admin_GO_OpenCalais class