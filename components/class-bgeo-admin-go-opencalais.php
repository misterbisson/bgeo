<?php
/*
This class includes code to integrate with https://github.com/GigaOM/go-opencalais.
*/

class bGeo_GO_OpenCalais extends bGeo
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
		add_filter( 'go_oc_response', array( $this, 'filter_response_add_terms' ), 2 );
	}

	public function filter_response_add_terms( $response )
	{
		foreach ( $response as $k => $v )
		{
			if ( isset( $v->_type, $v->name, $this->entity_types[ $v->_type ] ) )
			{
//print_r( $v );

				switch ( $v->_type )
				{
					case 'City':

						// don't bother with City entities without resolutions
						if ( ! isset( $v->resolutions ) )
						{
							unset( $response[ $k ] );
							continue( 2 );
						}

						$response[ $k ]->name = $v->resolutions[0]->shortname . ' ('. ( 'United States' == $v->resolutions[0]->containedbycountry ? $v->resolutions[0]->containedbystate : $v->resolutions[0]->containedbycountry ). ')';
						break;

					case 'ProvinceOrState':

						// don't bother with ProvinceOrState entities without resolutions
						if ( ! isset( $v->resolutions ) )
						{
							unset( $response[ $k ] );
							continue( 2 );
						}

						$response[ $k ]->name = $v->resolutions[0]->shortname . ' ('. $v->resolutions[0]->containedbycountry . ')';
						break;

					case 'Country':
						break;

					case 'Continent':
						break;

					case 'Region':
						break;

					default:
						break;

				}

//print_r( $v );
			}
		}

//print_r( $response );
//die;
		return $response;
	}//end filter_response_add_terms

}//end bGeo_GO_OpenCalais class