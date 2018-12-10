document.addEventListener('DOMContentLoaded', function () {

  const settings = Joomla.getOptions('plg_content_pbcontactmap');
  const places = Joomla.getOptions('plg_content_pbcontactmap_places');
  
  // cf. http://leaflet-extras.github.io/leaflet-providers/preview/
  const providers = [
    {layer: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'},
    {layer: 'http://{s}.tiles.wmflabs.org/bw-mapnik/{z}/{x}/{y}.png', attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'},
    {layer: 'https://{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'},
    {layer: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Tiles courtesy of <a href="http://hot.openstreetmap.org/" target="_blank">Humanitarian OpenStreetMap Team</a>'},
    {layer: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', attribution: 'Map data: &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'}
  ]
  
  var layer = providers[0].layer;
  var attribution = providers[0].attribution;
  if (typeof settings.layer != 'undefined') {
    var key = Number(settings.layer);
    layer = providers[key].layer;
    attribution = providers[key].attribution;
  }

  const $maps = getAll('.pbcontactmap');
  
  if ($maps.length > 0) {

    $maps.forEach(function (el) {

      document.getElementById(el.id).innerHTML = '';
      var map = L.map(el.id);

      L.tileLayer(layer, { attribution: attribution + ', <a href="https://operations.osmfoundation.org/policies/nominatim/">Nominatim</a>' }).addTo(map);

      // no [data-id] attribute, show all available markers
      if (typeof el.dataset.id == 'undefined') {

        var markers = [];

        map.setView([0, 0], 15);

        places.forEach(function (place) {
          var marker = L.marker([place.lat, place.lon], {link: place.link}).addTo(map);
          if (place.link != '') marker.on('click', onClick);
          if (place.name != '') marker.bindPopup(place.name, {closeButton: false}).on('mouseover', onMouseOver).on('mouseout', onMouseOut);
          markers.push(marker);
        });

        var group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds());

      } else {

        var contact_id = el.dataset.id;
        var place = places.find(getOptionsData, contact_id);
        var bounds = JSON.parse(place.boundingbox);
        
        map.setView([place.lat, place.lon], 15);

        var marker = L.marker([place.lat, place.lon]).addTo(map);
        if (place.name != '') marker.bindPopup(place.name, {closeButton: false}).on('mouseover', onMouseOver).on('mouseout', onMouseOut);
      }

      el.classList.remove('is-loading');
    });

  }
});

function getAll(selector) {
  return Array.prototype.slice.call(document.querySelectorAll(selector), 0);
}

function getOptionsData(data, id) {
  return data.contact_id == Number(this);
}

function onClick(e) {
  var link = this.options.link;
  
  /* parse the link in the browsers native html parser, cf. https://stackoverflow.com/questions/3700326/decode-amp-back-to-in-javascript */
  var parser = new DOMParser;
  var dom = parser.parseFromString('<!doctype html><body>' + link, 'text/html');
  var decodedLink = dom.body.textContent;
  
  location.href = decodedLink;
}

function onMouseOver(e) {
  this.openPopup();
}

function onMouseOut(e) {
  this.closePopup();
}
