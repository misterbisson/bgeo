<?php
/**
 * html for the metabox for adding locations to a post.
 */
?>

<div ng-app="bGeo">
	<section ng-controller="PostController as postCtrl">
		<ul>
			<li ng-repeat="location in postCtrl.locations">
				<a ng-click="postCtrl.removeLocation(location)"><i class="fa fa-times-circle"></i></a> {{location.name}} ({{location.description}})
				<input type="checkbox" checked name="bgeo[term][{{location.slug}}]" value="1">{{location.slug}}</li>
			</li>
		</ul>

		<section ng-controller="SuggestionsController as suggestionsCtrl">
			<input ng-model="suggestionsCtrl.searchText" type="text" class="form-control" placeholder="Enter an address or placename here" title="Search" />
			<a ng-click="suggestionsCtrl.searchLocations()">Search locations</a>

			<ul >
				<li ng-repeat="location in suggestionsCtrl.suggestions" ng-hide="suggestionsCtrl.isAccepted(location)">
					<a ng-click="suggestionsCtrl.acceptSuggestion(location)">{{location.name}} ({{location.description}})</a>
				</li>
			</ul>

			<a ng-click="suggestionsCtrl.getSuggestions()">get suggestions</a>
		</section>
	</section>
</div>