<?php
/**
 * html for the geographic details metabox when editing terms.
 */

$geo = bgeo()->get_geo( $tag->term_id, $tag->taxonomy );

// create a default geo object if the above failed
if( ! is_object( $geo ))
{
	$geo = (object) array(
		'point' => NULL,
		'point_lat' => NULL,
		'point_lon' => NULL,
		'bounds' => NULL,
	);
}

wp_localize_script( 'bgeo-admin-terms', 'bgeo_term', (array) $geo );

?>
<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo bgeo()->admin()->get_field_id( 'map' ); ?>">Map</label>
	</th>
	<td>
		<div id="<?php echo bgeo()->admin()->get_field_id( 'map' ); ?>"></div>
	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo bgeo()->admin()->get_field_id( 'point' ); ?>">Point</label>
	</th>
	<td>
		<input type="text" id="<?php echo bgeo()->admin()->get_field_id( 'point_lat' ); ?>" name="<?php echo bgeo()->admin()->get_field_name( 'point_lat' ); ?>" value="<?php echo( esc_attr( $geo->point_lat ) ); ?>" size="10" />
		<input type="text" id="<?php echo bgeo()->admin()->get_field_id( 'point_lon' ); ?>" name="<?php echo bgeo()->admin()->get_field_name( 'point_lon' ); ?>" value="<?php echo( esc_attr( $geo->point_lon ) ); ?>" size="10" />
	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo bgeo()->admin()->get_field_id( 'bounds' ); ?>">Bounds</label>
	</th>
	<td>
		<input type="text" id="<?php echo bgeo()->admin()->get_field_id( 'bounds' ); ?>" name="<?php echo bgeo()->admin()->get_field_name( 'bounds' ); ?>" value="<?php echo( esc_attr( $geo->bounds ) ); ?>" size="10" />
	</td>
</tr>