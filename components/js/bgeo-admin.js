(function($){
	var tile_url = 'http://otile1.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png';
	var tile_attribution = 'Map data &copy; <a href="http://openstreetmap.org" target="_blank">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/" target="_blank">CC-BY-SA</a>, tiles courtesy of <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>';
	
	var map = L.map('bgeo-map', {
		scrollWheelZoom: false
	}).setView([37.78672, -122.39937], 12);
	
	L.tileLayer( tile_url, {
		attribution: tile_attribution,
		maxZoom: 18,
		styleId: 997
	}).addTo( map );

	function onMapClick(e) {
		//alert("You clicked the map at " + e.latlng);
		$('#bgeo-coordinates-lat').val( e.latlng.lat );
		$('#bgeo-coordinates-lon').val( e.latlng.lng );
	}

	map.on('click', onMapClick);
})(jQuery);

