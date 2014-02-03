<?php
/**
 * html for the Details metabox.
 * The context is the bDefinite_Admin class, where $post is set.
 */

$geo = $this->get_geo( $tag->term_id, $tag->taxonomy );

// create a default geo obgect if the above failed
if( ! is_object( $geo ))
{
	$geo = (object) array(
		'point' => NULL,
		'point_lat' => NULL,
		'point_lon' => NULL,
		'bounds' => NULL,
	);
}

wp_localize_script( $this->id_base . '-admin', $this->id_base . '_term', (array) $geo );

?>
<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo $this->get_field_id( 'map' ); ?>">Map</label>
	</th>
	<td>
		<div id="<?php echo $this->get_field_id( 'map' ); ?>"></div>
	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo $this->get_field_id( 'point' ); ?>">Point</label>
	</th>
	<td>
		<input type="text" id="<?php echo $this->get_field_id( 'point_lat' ); ?>" name="<?php echo $this->get_field_name( 'point_lat' ); ?>" value="<?php echo( esc_attr( $geo->point_lat ) ); ?>" size="10" />
		<input type="text" id="<?php echo $this->get_field_id( 'point_lon' ); ?>" name="<?php echo $this->get_field_name( 'point_lon' ); ?>" value="<?php echo( esc_attr( $geo->point_lon ) ); ?>" size="10" />
	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo $this->get_field_id( 'bounds' ); ?>">Bounds</label>
	</th>
	<td>
		<input type="text" id="<?php echo $this->get_field_id( 'bounds' ); ?>" name="<?php echo $this->get_field_name( 'bounds' ); ?>" value="<?php echo( esc_attr( $geo->bounds ) ); ?>" size="10" />
	</td>
</tr>