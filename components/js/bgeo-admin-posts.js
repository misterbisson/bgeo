(function() {
	var app = angular.module('bGeo', []);

	app.controller('PostController', function() {
		this.locations = app.postLocations;

		this.removeLocation = function(location) {
			app.suggestedLocations[location.term_taxonomy_id] = location;
			delete this.locations[location.term_taxonomy_id];
		}
	});

	app.controller('SuggestionsController', ['$http', function($http){
		var api = this;
		api.suggestions = app.suggestedLocations;

		this.acceptSuggestion = function(location) {
			app.postLocations[location.term_taxonomy_id] = location;
			delete api.suggestions[location.term_taxonomy_id];
		}

		this.isAccepted = function(location) {
			// is the suggested location present on the post?
			if( 
				undefined == app.postLocations[location.term_taxonomy_id] ||
				app.postLocations[location.term_taxonomy_id].term_taxonomy_id != location.term_taxonomy_id)
			{
				return false;
			}

			// remove the location from the suggestion stack if it's present on the post
			delete api.suggestions[location.term_taxonomy_id];
			return true;
		}

		this.getSuggestions = function() {
			var url = 'http://bgeo.me/wp-admin/admin-ajax.php?action=bgeo-locationsfromtext&post_id=' + bgeo.post_id + '&nonce=' + bgeo.nonce;
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

	app.postLocations = {
		1222: {
			"term_id": 1222,
			"name": "Zipcode 95811",
			"slug": "55805667-95811",
			"term_group": 0,
			"term_taxonomy_id": 1222,
			"taxonomy": "bgeo_tags",
			"description": "",
			"parent": 0,
			"count": 0,
			"filter": "raw",
			"point": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Point\",\"coordinates\":[-121.478203,38.583931]}}",
			"bounds": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[-121.473701,38.564941],[-121.473701,38.603161],[-121.510567,38.603161],[-121.510567,38.564941],[-121.473701,38.564941]]]}}",
			"area": "0",
			"woeid": "55805667",
			"woe_belongtos": [
				2486340,
				12587703,
				24701635,
				24702800,
				23511731,
				55863341,
				55857166,
				2347563,
				24875670,
				23689941
			],
			"point_lat": 38.583931,
			"point_lon": -121.478203,
			"bounds_se": {
				"lat": 38.564941,
				"lon": -121.473701
			},
			"bounds_ne": {
				"lat": 38.603161,
				"lon": -121.473701
			},
			"bounds_nw": {
				"lat": 38.603161,
				"lon": -121.510567
			},
			"bounds_sw": {
				"lat": 38.564941,
				"lon": -121.510567
			}
		},
		222: {
			"term_id": 222,
			"name": "95811",
			"slug": "55805667-95811",
			"term_group": 0,
			"term_taxonomy_id": 222,
			"taxonomy": "bgeo_tags",
			"description": "",
			"parent": 0,
			"count": 0,
			"filter": "raw",
			"point": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Point\",\"coordinates\":[-121.478203,38.583931]}}",
			"bounds": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[-121.473701,38.564941],[-121.473701,38.603161],[-121.510567,38.603161],[-121.510567,38.564941],[-121.473701,38.564941]]]}}",
			"area": "0",
			"woeid": "55805667",
			"woe_belongtos": [
				2486340,
				12587703,
				24701635,
				24702800,
				23511731,
				55863341,
				55857166,
				2347563,
				24875670,
				23689941
			],
			"point_lat": 38.583931,
			"point_lon": -121.478203,
			"bounds_se": {
				"lat": 38.564941,
				"lon": -121.473701
			},
			"bounds_ne": {
				"lat": 38.603161,
				"lon": -121.473701
			},
			"bounds_nw": {
				"lat": 38.603161,
				"lon": -121.510567
			},
			"bounds_sw": {
				"lat": 38.564941,
				"lon": -121.510567
			}
		}
	};

	app.suggestedLocations = {
		221: {
			"term_id": 221,
			"name": "Sacramento",
			"slug": "2486340-sacramento",
			"term_group": 0,
			"term_taxonomy_id": 221,
			"taxonomy": "bgeo_tags",
			"description": "",
			"parent": 0,
			"count": 0,
			"filter": "raw",
			"point": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Point\",\"coordinates\":[-121.468849,38.56789]}}",
			"bounds": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[-121.362701,38.43779],[-121.362701,38.6856],[-121.560509,38.6856],[-121.560509,38.43779],[-121.362701,38.43779]]]}}",
			"area": "4",
			"woeid": "2486340",
			"woe_belongtos": [
				12587703,
				24701635,
				24702800,
				23511731,
				55863341,
				55857166,
				2347563,
				24875670,
				23689941,
				56043663
			],
			"point_lat": 38.56789,
			"point_lon": -121.468849,
			"bounds_se": {
				"lat": 38.43779,
				"lon": -121.362701
			},
			"bounds_ne": {
				"lat": 38.6856,
				"lon": -121.362701
			},
			"bounds_nw": {
				"lat": 38.6856,
				"lon": -121.560509
			},
			"bounds_sw": {
				"lat": 38.43779,
				"lon": -121.560509
			}
		},
		222: {
			"term_id": 222,
			"name": "95811",
			"slug": "55805667-95811",
			"term_group": 0,
			"term_taxonomy_id": 222,
			"taxonomy": "bgeo_tags",
			"description": "",
			"parent": 0,
			"count": 0,
			"filter": "raw",
			"point": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Point\",\"coordinates\":[-121.478203,38.583931]}}",
			"bounds": "{\"type\":\"Feature\",\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[-121.473701,38.564941],[-121.473701,38.603161],[-121.510567,38.603161],[-121.510567,38.564941],[-121.473701,38.564941]]]}}",
			"area": "0",
			"woeid": "55805667",
			"woe_belongtos": [
				2486340,
				12587703,
				24701635,
				24702800,
				23511731,
				55863341,
				55857166,
				2347563,
				24875670,
				23689941
			],
			"point_lat": 38.583931,
			"point_lon": -121.478203,
			"bounds_se": {
				"lat": 38.564941,
				"lon": -121.473701
			},
			"bounds_ne": {
				"lat": 38.603161,
				"lon": -121.473701
			},
			"bounds_nw": {
				"lat": 38.603161,
				"lon": -121.510567
			},
			"bounds_sw": {
				"lat": 38.564941,
				"lon": -121.510567
			}
		}
	};
})();