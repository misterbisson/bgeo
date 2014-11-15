(function() {
	var app = angular.module('bGeo', []);

	app.controller('PostController', function() {
		this.locations = bgeo.post_geos;

		this.removeLocation = function(location) {
			app.suggestedLocations[location.term_taxonomy_id] = location;
			delete this.locations[location.term_taxonomy_id];
		}
	});

	app.controller('SuggestionsController', ['$http', function($http){
		var api = this;
//		api.suggestions = app.suggestedLocations;
		api.suggestions = {};

		this.acceptSuggestion = function(location) {
			bgeo.post_geos[location.term_taxonomy_id] = location;
			delete this.suggestions[location.term_taxonomy_id];
		}

		this.isAccepted = function(location) {
			// is the suggested location present on the post?
			if( 
				undefined == bgeo.post_geos[location.term_taxonomy_id] ||
				bgeo.post_geos[location.term_taxonomy_id].term_taxonomy_id != location.term_taxonomy_id)
			{
				return false;
			}

			// remove the location from the suggestion stack if it's present on the post
			delete this.suggestions[location.term_taxonomy_id];
			return true;
		}

		this.getSuggestions = function() {
			var url = bgeo.endpoint + '&post_id=' + bgeo.post_id + '&nonce=' + bgeo.nonce;
			$http.get(url).success(function (data) {
				// sanity check
				firstKey = Object.keys(data)[0];
				if(undefined == data[firstKey])
				{
					return;
				}

				api.suggestions = data;
			});
		}

	}]);

	app.postLocations = {};

	app.suggestedLocations = {};
})();