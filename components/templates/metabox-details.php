<?php
/**
 * html for the Details metabox. 
 * The context is the bDefinite_Admin class, where $post is set.
 */

//$meta = $this->get_meta( $post->ID );
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
		<label for="<?php echo $this->get_field_id( 'coordinates' ); ?>">Coordinates</label>
	</th>
	<td>
		<input type="text" id="<?php echo $this->get_field_id( 'coordinates-lat' ); ?>" name="<?php echo $this->get_field_name( 'coordinates-lat' ); ?>" value="<?php echo( esc_attr( $meta['coordinates-lat'] ) ); ?>" size="10" />
		<input type="text" id="<?php echo $this->get_field_id( 'coordinates-lon' ); ?>" name="<?php echo $this->get_field_name( 'coordinates-lon' ); ?>" value="<?php echo( esc_attr( $meta['coordinates-lon'] ) ); ?>" size="10" />
	</td>
</tr>