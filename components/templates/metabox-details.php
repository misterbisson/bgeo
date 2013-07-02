<?php
/**
 * html for the Details metabox. 
 * The context is the bDefinite_Admin class, where $post is set.
 */

//$meta = $this->get_meta( $post->ID );
?>
<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo $this->get_field_id( 'word' ); ?>">Word</label>
	</th>
	<td>
		<input type="text" id="<?php echo $this->get_field_id( 'word' ); ?>" name="<?php echo $this->get_field_name( 'word' ); ?>" value="<?php echo( esc_attr( $meta['word'] ) ); ?>" size="25" />
	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo $this->get_field_id( 'pronunciation' ); ?>">Pronunciation</label>
	</th>
	<td>
		<input type="text" id="<?php echo $this->get_field_id( 'pronunciation' ); ?>" name="<?php echo $this->get_field_name( 'pronunciation' ); ?>" value="<?php echo( esc_attr( $meta['pronunciation'] ) ); ?>" size="25" />
	</td>
</tr>