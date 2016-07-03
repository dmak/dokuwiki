/**
 * Plugin google_maps: Generates embedded Google Maps frame or link to Google Maps.
 *
 * @license    GPLv2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Dmitry Katsubo <dma_k@mail.ru>
 */

(function() {
// Globals:
var GMAPS_MAX_RETRY_COUNT = 5;
var GMAPS_RETRY_DELAY = 100;
var GMAPS_MAX_GEO_RESULTS = 1;
var GMAPS_GEOCODER = null;

/*
 * This function creates a new marker with a given HTML shown when a marker is clicked.
 */
function createMarker(point, desc)
{
	var marker = new GMarker(point);

	// Note: Without wrapping into a function, listeners are added to the same objects!
	GEvent.addListener(marker, "click", function()
	{
		marker.openInfoWindowHtml(desc);
	});

	return marker;
}

/*
 * This recursive function sends an ansynchronous query to Google GeoCoder and marks results on the map.
 */
function queryGoogleGeo(map, bounds, locations, index, zoom, retry)
{
	if (GMAPS_GEOCODER == null)
	{
		// Can be initialized only at this point, as Google libraries should have been included:
		GMAPS_GEOCODER = new GClientGeocoder();
	}

	GMAPS_GEOCODER.getLocations(locations[index],
		function generateMarkersFromGoogleGeoResult(response)
		{
			// Was not able to locate any data:
			if (response == null)
			{
				alert("No response from GeoCoder for location " + locations[index] + ". Giving up.");
				return;
			}
			else if (response.Status.code == 602)
			{
				if (retry++ >= GMAPS_MAX_RETRY_COUNT)
				{
					alert("The maximum amount of retries (" + GMAPS_MAX_RETRY_COUNT + ") has been reached for location " + locations[index] + ". Giving up.");
					return;
				}

				setTimeout(queryGoogleGeo, GMAPS_RETRY_DELAY, map, bounds, locations, index, zoom, retry);
				return;
			}
			else if (response.Status.code != 200)
			{
				alert("Invalid response code (" + response.Status.code + ") from GeoCoder for location " + locations[index] + ". Giving up.");
				return;
			}

			var places = response.Placemark;

			for (var i = 0; i < places.length && i < GMAPS_MAX_GEO_RESULTS; i++)
			{
				var place = places[i];
				var point = new GLatLng(place.Point.coordinates[1], place.Point.coordinates[0]);

				bounds.extend(point);

				map.addOverlay(createMarker(point, '<div class="gmaps_marker"><strong>' + place.address + '</strong><br/>'
					+ place.AddressDetails.Country.CountryNameCode
				));
			}

			if (index == locations.length - 1)
			{
				if (zoom == null)
				{
					if (!bounds.isEmpty())
					{
						// We select the best zoom for the boundary:
						zoom = map.getBoundsZoomLevel(bounds);
					}
				}
				else
				{
					// zoom is required to be an integer:
					zoom = parseInt(zoom);
				}

				map.setCenter(bounds.getCenter(), zoom);
			}
			else
			{
				// Query recuresively other locations:
				queryGoogleGeo(map, bounds, locations, index + 1, zoom, retry);
			}
		});
}

/**
 * Initialisation function. Creates Gmap objects and loads Geo information.
 */
function loadMaps()
{
	jQuery('div.gmaps_frame').each(function() {
		var attrs = this.attributes;

		// Create a map:
		var map = new GMap2(this);
		map.setCenter(new GLatLng(34, 0), 1); // default point

		// left-top navigator and zoomer
		if (attrs.size.value == 'small')
			map.addControl(new GSmallMapControl());
		else if (attrs.size.value == 'large')
			map.addControl(new GLargeMapControl());

		// right-top map type switch buttons
		if (attrs.control.value == 'hierarchical')
			map.addControl(new GHierarchicalMapTypeControl());
		else if (attrs.control.value == 'all')
			map.addControl(new GMapTypeControl());

		// mini-map in the bottom-right corner
		if (attrs.overviewmap.value == 'true')
		{
			var overviewMap = new GOverviewMapControl();
			map.addControl(overviewMap);
			overviewMap.hide();
		}

		map.enableScrollWheelZoom();

		var locations = new Array();

		var n = 0;
		while (true)
		{
			if (attrs['location' + n] == null)
			{
				break;
			}

			locations[n] = attrs['location' + n].value;
			n++;
		}

		queryGoogleGeo(map, new GLatLngBounds(), locations, 0, attrs.zoom == null ? null : attrs.zoom.value, 0);
	});
}

// A special Wiki-wide function, defined in lib/scripts/events.js:
jQuery(loadMaps);
})();