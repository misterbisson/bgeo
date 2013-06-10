<?php
/**
 * html for the Details metabox. 
 * The context is the bDefinite_Admin class, where $post is set.
 */

$meta = $this->get_meta( $post->ID );
?>
<div class="fields">
	<p>
		<label for="<?php echo $this->get_field_id( 'word' ); ?>"><strong>Word</strong></label><br />
		<input type="text" id="<?php echo $this->get_field_id( 'word' ); ?>" name="<?php echo $this->get_field_name( 'word' ); ?>" value="<?php echo( esc_attr( $meta['word'] ) ); ?>" size="25" />
	</p>
	<p>
		<label for="<?php echo $this->get_field_id( 'pronunciation' ); ?>"><strong>Pronunciation</strong></label><br />
		<input type="text" id="<?php echo $this->get_field_id( 'pronunciation' ); ?>" name="<?php echo $this->get_field_name( 'pronunciation' ); ?>" value="<?php echo( esc_attr( $meta['pronunciation'] ) ); ?>" size="25" />
	</p>
	<p>
		<label for="<?php echo $this->get_field_id( 'partofspeech' ); ?>"><strong>Part of speech</strong></label><br />
		<?php $this->control_partsofspeech( 'partofspeech', $meta ); ?>
	</p>
</div>