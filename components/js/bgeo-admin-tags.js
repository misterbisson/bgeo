(function($){

var statesData = {"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[-71.120168,41.321569],[-71.120168,42.01714],[-71.859555,42.01714],[-71.859555,41.321569],[-71.120168,41.321569]]]}};

	var tile_url = 'http://otile1.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png';
	var tile_attribution = 'Map data &copy; <a href="http://openstreetmap.org" target="_blank">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/" target="_blank">CC-BY-SA</a>, tiles courtesy of <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>';

	var start_lat = ( ! bgeo_term.point_lat ) ? '37.78672' : bgeo_term.point_lat ;
	var start_lon = ( ! bgeo_term.point_lon ) ? '-122.39937': bgeo_term.point_lon ;

	var map = L.map('bgeo-map', {
		scrollWheelZoom: false
	}).setView([ start_lat, start_lon ], 4);

	L.tileLayer( tile_url, {
		attribution: tile_attribution,
		maxZoom: 18,
		styleId: 997
	}).addTo( map );


	if( bgeo_term.bounds ) {
		L.geoJson( $.parseJSON( bgeo_term.bounds ) ).addTo(map);
		L.geoJson( $.parseJSON( bgeo_term.point ) ).addTo(map);
	}

	if( bgeo_term.bounds_sw && bgeo_term.bounds_ne ) {
		map.fitBounds([
		    [bgeo_term.bounds_sw.lat, bgeo_term.bounds_sw.lon]
		    [bgeo_term.bounds_ne.lat, bgeo_term.bounds_ne.lon],
		]);
	}
	function onMapClick(e) {
		//alert("You clicked the map at " + e.latlng);
		$('#bgeo-point_lat').val( e.latlng.lat );
		$('#bgeo-point_lon').val( e.latlng.lng );
	}

	map.on('click', onMapClick);
})(jQuery);

