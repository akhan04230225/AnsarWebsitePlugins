(function(){
  function initMap() {
    var markers = window.IUSM_MARKERS || [];
    var map = new google.maps.Map(document.getElementById('iusm-map'), {
      zoom: 4,
      center: {lat: 39.8283, lng: -98.5795}, // Center of contiguous US
      mapTypeId: 'roadmap'
    });
    var normalIcon = {
      path: google.maps.SymbolPath.CIRCLE,
      scale: 6,
      fillColor: '#ff0000',
      fillOpacity: 1,
      strokeWeight: 1,
      strokeColor: '#ffffff'
    };
    var glowIcon = {
      path: google.maps.SymbolPath.CIRCLE,
      scale: 10,
      fillColor: '#ff0000',
      fillOpacity: 1,
      strokeWeight: 1,
      strokeColor: '#ffffff'
    };
    markers.forEach(function(c){
      var marker = new google.maps.Marker({
        position: {lat: c.lat, lng: c.lng},
        map: map,
        title: c.label,
        icon: normalIcon
      });
      marker.addListener('mouseover', function(){ marker.setIcon(glowIcon); });
      marker.addListener('mouseout', function(){ marker.setIcon(normalIcon); });
      marker.addListener('click', function(){ window.open(c.url, '_blank'); });
    });
  }
  if (window.google && window.google.maps) {
    initMap();
  } else {
    window.initIUSMMap = initMap;
  }
})();
