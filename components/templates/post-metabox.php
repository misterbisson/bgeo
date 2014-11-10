<?php
/**
 * html for the metabox for adding locations to a post.
 */

/*
<script>
	if ( ! scrib_authority_data ) {
		var scrib_authority_data = {};
	}//end if
	scrib_authority_data['children'] = <?php echo json_encode( $children_prep->detail ); ?>;
	scrib_authority_data['parents'] = <?php echo json_encode( $parents_prep->detail ); ?>;
</script>
*/
?>

<div ng-app="bGeo">
	<section ng-controller="PostController as postCtrl">
		<ul>
			<li ng-repeat="location in postCtrl.locations">
				<a data-ttid="{{location.term_taxonomy_id}}">{{location.name}}</a> {{postCtrl.thang}}
			</li>
		</ul>
		<section ng-controller="SuggestionsController as suggestionsCtrl">
			<ul >
				<li ng-repeat="location in suggestionsCtrl.suggestions">
					<a ng-click="suggestionsCtrl.clickr(location, postCtrl)" data-ttid="{{location.term_taxonomy_id}}">{{location.name}}</a>  {{postCtrl.thang}}
				</li>
			</ul>
		</section>
	</section>
</div>

<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo bgeo()->admin()->get_field_id( 'locations' ); ?>">Locations named in this post</label>
	</th>
	<td>
		<textarea rows="3" cols="50" name="<?php echo bgeo()->admin()->get_field_name( 'locations' ); ?>" id="<?php echo bgeo()->admin()->get_field_id( 'locations' ); ?>"><?php echo implode( ', ' , $locations ); ?></textarea>
	</td>
</tr>

<tr class="form-field">
	<th scope="row" valign="top">
		<label for="<?php echo bgeo()->admin()->get_field_id( 'locations-larger' ); ?>">The regions enclosing the locations in the post</label>
	</th>
	<td>
		<textarea rows="3" cols="50" name="<?php echo bgeo()->admin()->get_field_name( 'locations-belongtos' ); ?>" id="<?php echo bgeo()->admin()->get_field_id( 'locations-larger' ); ?>"><?php echo implode( ', ' , $locations_belongtos ); ?></textarea>
	</td>
</tr>