(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.geofieldMapInit = {
    attach: function (context, drupalSettings) {

      // Init all maps in drupalSettings.
      if (drupalSettings['geofield_map']) {
        $.each(drupalSettings['geofield_map'], function (mapid, options) {

          // Define the first map id, for a multivalue geofield map.
          if (mapid.indexOf('0-value') !== -1) {
            Drupal.geoFieldMap.firstMapId = mapid;
          }
          // Check if the Map container really exists and hasn't been yet initialized.
          if ($('#' + mapid, context).length > 0 && !Drupal.geoFieldMap.map_data[mapid]) {

            // Set the map_data[mapid] settings.
            Drupal.geoFieldMap.map_data[mapid] = options;

            if (options.gmap_api_key || options.map_library === 'gmap') {
              // Load before the Gmap Library, if needed.
              Drupal.geoFieldMap.loadGoogle(mapid, function () {
                Drupal.geoFieldMap.map_initialize(options);
              });
            }
            else {
              Drupal.geoFieldMap.map_initialize(options);
            }
          }
        });
      }
    }
  };

  Drupal.geoFieldMap = {

    geocoder: null,
    map_data: {},
    firstMapId: null,

    // Google Maps are loaded lazily. In some situations load_google() is called twice, which results in
    // "You have included the Google Maps API multiple times on this page. This may cause unexpected errors." errors.
    // This flag will prevent repeat $.getScript() calls.
    maps_api_loading: false,

    /**
     * Provides the callback that is called when maps loads.
     */
    googleCallback: function () {
      var self = this;
      // Wait until the window load event to try to use the maps library.
      $(document).ready(function (e) {
        _.invoke(self.googleCallbacks, 'callback');
        self.googleCallbacks = [];
      });
    },

    /**
     * Adds a callback that will be called once the maps library is loaded.
     *
     * @param callback - The callback
     */
    addCallback: function (callback) {
      var self = this;
      // Ensure callbacks array;
      self.googleCallbacks = self.googleCallbacks || [];
      self.googleCallbacks.push({callback: callback});
    },

    // Lead Google Maps library.
    loadGoogle: function (mapid, callback) {
      var self = this;

      // Add the callback.
      self.addCallback(callback);

      // Check for google maps.
      if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        if (self.maps_api_loading === true) {
          return;
        }

        self.maps_api_loading = true;
        // Google maps isn't loaded so lazy load google maps.

        // Default script path.
        var scriptPath = '//maps.googleapis.com/maps/api/js?v=3.exp&sensor=false';

        // If a Google API key is set, use it.
        if (typeof self.map_data[mapid]['gmap_api_key'] !== 'undefined' && self.map_data[mapid]['gmap_api_key'] !== null) {
          scriptPath += '&key=' + self.map_data[mapid]['gmap_api_key'];
        }

        $.getScript(scriptPath)
          .done(function () {
            self.maps_api_loading = false;
            self.googleCallback();
          });

      }
      else {
        // Google maps loaded. Run callback.
        self.googleCallback();
      }
    },

    // Center the map to the marker position.
    find_marker: function (mapid) {
      var self = this;
      self.mapSetCenter(mapid, self.getMarkerPosition(mapid));
    },

    // Place marker at the current center of the map.
    place_marker: function (mapid) {
      var self = this;
      if (self.map_data[mapid].click_to_place_marker) {
        if (!window.confirm('Change marker position ?')) {
          return;
        }
      }
      var position = self.map_data[mapid].map.getCenter();
      self.setMarkerPosition(mapid, position);
      self.geofields_update(mapid, position);
    },

    // Geofields update.
    geofields_update: function (mapid, position) {
      var self = this;
      self.setLatLngValues(mapid, position);
      self.reverse_geocode(mapid, position);
    },

    // Onchange of Geofields.
    geofield_onchange: function (mapid) {
      var self = this;
      var position = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          position = L.latLng(
            self.map_data[mapid].lat.val(),
            self.map_data[mapid].lng.val()
          );
          break;
        default:
          position = new google.maps.LatLng(
            self.map_data[mapid].lat.val(),
            self.map_data[mapid].lng.val()
          );
      }
      self.setMarkerPosition(mapid, position);
      self.mapSetCenter(mapid, position);
      self.setZoomToFocus(mapid);
      self.reverse_geocode(mapid, position);
    },

    // Coordinates update.
    setLatLngValues: function (mapid, position) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].lat.val(position.lat.toFixed(6));
          self.map_data[mapid].lng.val(position.lng.toFixed(6));
          break;
        default:
          self.map_data[mapid].lat.val(position.lat().toFixed(6));
          self.map_data[mapid].lng.val(position.lng().toFixed(6));
      }
    },

    // Reverse geocode.
    reverse_geocode: function (mapid, position) {
      var self = this;
      if (self.geocoder) {
        self.geocoder.geocode({latLng: position}, function (results, status) {
          if (status === google.maps.GeocoderStatus.OK && results[0]) {
            if (self.map_data[mapid].search) {
              self.map_data[mapid].search.val(results[0].formatted_address);
              self.setGeoaddressField(mapid, self.map_data[mapid].search.val());
            }
          }
        });
      }
      return status;
    },

    // Triggers the Geocode on the Geofield Map Widget
    trigger_geocode: function(mapid, position) {
      var self = this;
      self.setMarkerPosition(mapid, position);
      self.mapSetCenter(mapid, position);
      self.setZoomToFocus(mapid);
      self.setLatLngValues(mapid, position);
      self.setGeoaddressField(mapid, self.map_data[mapid].search.val());
    },

    // Define a Geographical point, from coordinates.
    getLatLng: function (mapid, lat, lng) {
      var self = this;
      var latLng = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          latLng = L.latLng(lat, lng);
          break;
        default:
          latLng = new google.maps.LatLng(lat, lng);
      }
      return latLng;
    },

    // Define the Geofield Map.
    getGeofieldMap: function (mapid) {
      var self = this;
      var map = {};
      var zoom_start = self.map_data[mapid].entity_operation !== 'edit' ? Number(self.map_data[mapid].zoom_start) : Number(self.map_data[mapid].zoom_focus);
      var zoom_min = Number(self.map_data[mapid].zoom_min);
      var zoom_max = Number(self.map_data[mapid].zoom_max);
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          map = L.map(mapid, {
            center: self.map_data[mapid].position,
            zoom: zoom_start,
            minZoom: zoom_min,
            maxZoom: zoom_max
          });

          var baseLayers = {};
          for (var key in self.map_data[mapid].map_types_leaflet) {
            if (self.map_data[mapid].map_types_leaflet.hasOwnProperty(key)) {
              baseLayers[key] = L.tileLayer(self.map_data[mapid].map_types_leaflet[key].url, self.map_data[mapid].map_types_leaflet[key].options);
            }
          }
          baseLayers[self.map_data[mapid].map_type].addTo(map);
          if (self.map_data[mapid].map_type_selector) {
            L.control.layers(baseLayers).addTo(map);
          }

          break;

        default:
          var options = {
            zoom: zoom_start,
            minZoom: zoom_min,
            maxZoom: zoom_max,
            center: self.map_data[mapid].position,
            mapTypeId: self.map_data[mapid].map_type,
            mapTypeControl: !!self.map_data[mapid].map_type_selector,
            mapTypeControlOptions: {
              position: google.maps.ControlPosition.TOP_RIGHT
            },
            scaleControl: true,
            streetViewControlOptions: {
              position: google.maps.ControlPosition.TOP_RIGHT
            },
            zoomControlOptions: {
              style: google.maps.ZoomControlStyle.LARGE,
              position: google.maps.ControlPosition.TOP_LEFT
            }
          };
          map = new google.maps.Map(document.getElementById(mapid), options);
      }
      return map;
    },

    setZoomToFocus: function (mapid) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].map.setZoom(self.map_data[mapid].zoom_focus, {animate: false});
          break;

        default:
          self.map_data[mapid].map.setZoom(self.map_data[mapid].zoom_focus);
      }
    },

    setMarker: function (mapid, position) {
      var self = this;
      var marker = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          marker = L.marker(position, {draggable: true});
          marker.addTo(self.map_data[mapid].map);
          break;

        default:
          marker = new google.maps.Marker({
            map: self.map_data[mapid].map,
            draggable: self.map_data[mapid].widget
          });
          marker.setPosition(position);
      }
      return marker;
    },

    setMarkerPosition: function (mapid, position) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].marker.setLatLng(position);
          break;

        default:
          self.map_data[mapid].marker.setPosition(position);
      }
    },

    getMarkerPosition: function (mapid) {
      var self = this;
      var latLng = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          latLng = self.map_data[mapid].marker.getLatLng();
          break;

        default:
          latLng = self.map_data[mapid].marker.getPosition();
      }
      return latLng;
    },

    mapSetCenter: function (mapid, position) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].map.panTo(position, {animate: false});
          break;

        default:
          self.map_data[mapid].map.setCenter(position);
      }
    },

    setGeoaddressField: function(mapid, address) {
      var self = this;
      if (mapid) {
        self.map_data[mapid].geoaddress_field.val(address);
      }
    },

    map_refresh: function (mapid) {
      var self = this;
      setTimeout(function() {
        google.maps.event.trigger(self.map_data[mapid].map, 'resize');
        self.find_marker(mapid);
      }, 10);
    },

    // Init Geofield Map and its functions.
    map_initialize: function (params) {
      var self = this;
      $.noConflict();

      if (params.searchid !== null) {

        // Define a google Geocoder, if not yet done.
        if (!self.geocoder) {
          self.geocoder = new google.maps.Geocoder();
        }

        // Define the Geocoder Search Field Selector;
        self.map_data[params.mapid].search = $('#' + params.searchid);

        // Define the Geoaddress Associated Field Selector;
        self.map_data[params.mapid].geoaddress_field = $('#' + params.geoaddress_field_id);

      }

      // Define the Geofield Position.
      var position = self.getLatLng(params.mapid, params.lat, params.lng);
      self.map_data[params.mapid].position = position;

      // Define the Geofield Map.
      var map = self.getGeofieldMap(params.mapid);

      // Define a map self property, so other code can interact with it.
      self.map_data[params.mapid].map = map;

      // Generate and Set/Place Marker Position.
      var marker = self.setMarker(params.mapid, position);

      // Define a Drupal.geofield_map marker self property.
      self.map_data[params.mapid].marker = marker;

      // Bind click to find_marker functionality.
      $('#' + self.map_data[params.mapid].click_to_find_marker_id).click(function (e) {
        e.preventDefault();
        self.find_marker(self.map_data[params.mapid].mapid);
      });

      // Bind click to place_marker functionality.
      $('#' + self.map_data[params.mapid].click_to_place_marker_id).click(function (e) {
        e.preventDefault();
        self.place_marker(self.map_data[params.mapid].mapid);
      });

      // Define Lat & Lng input selectors and all related functionalities and Geofield Map Listeners
      if (params.widget && params.latid && params.lngid) {
        self.map_data[params.mapid].lat = $('#' + params.latid);
        self.map_data[params.mapid].lng = $('#' + params.lngid);

        // If it is defined the Geocode address Search field (dependant on the Gmaps API key)
        if (self.map_data[params.mapid].search) {
          // Apply the Jquery Autocomplete widget, enabled by core/drupal.autocomplete
          self.map_data[params.mapid].search.autocomplete({
            // This bit uses the geocoder to fetch address values.
            source: function (request, response) {
              self.geocoder.geocode({address: request.term}, function (results, status) {
                response($.map(results, function (item) {
                  return {
                    // the value property is needed to be passed to the select.
                    value: item.formatted_address,
                    latitude: item.geometry.location.lat(),
                    longitude: item.geometry.location.lng()
                  };
                }));
              });
            },
            // This bit is executed upon selection of an address.
            select: function (event, ui) {
              // Update the Geocode address Search field value with the value (or label)
              // property that is passed as the selected autocomplete text
              self.map_data[params.mapid].search.val(ui.item.value);
              // Triggers the Geocode on the Geofield Map Widget
              var position = self.getLatLng(params.mapid, ui.item.latitude, ui.item.longitude);
              self.trigger_geocode(params.mapid, position);
            }
          });

          // Geocode user input on enter.
          self.map_data[params.mapid].search.keydown(function (e) {
            if (e.which === 13) {
              e.preventDefault();
              var input = self.map_data[params.mapid].search.val();
              // Execute the geocoder
              self.geocoder.geocode({address: input}, function (results, status) {
                if (status === google.maps.GeocoderStatus.OK && results[0]) {
                  // Triggers the Geocode on the Geofield Map Widget
                  var position = self.getLatLng(params.mapid, results[0].geometry.location.lat(), results[0].geometry.location.lng());
                  self.trigger_geocode(params.mapid, position);
                }
              });
            }
          });
        }

        if (params.map_library === 'gmap') {

          // Fix map issue in field_groups / details & vertical tabs
          google.maps.event.addListenerOnce(map, "idle", function () {

            // Show all map tiles when a map is shown in a vertical tab.
            $('#' + params.mapid).closest('div.vertical-tabs').find('.vertical-tabs__menu-item a').click(function () {
              self.map_refresh(params.mapid);
            });

            // Show all map tiles when a map is shown in a collapsible detail/ single tab.
            $('#' + params.mapid).closest('.field-group-details, .field-group-tab').find('summary').click(function () {
                self.map_refresh(params.mapid);
              }
            );
          });

          // Add listener to marker for reverse geocoding.
          google.maps.event.addListener(marker, 'dragend', function () {
            self.geofields_update(params.mapid, marker.getPosition());
          });

          // Change marker position with mouse click.
          google.maps.event.addListener(map, 'click', function (event) {
            var position = self.getLatLng(params.mapid, event.latLng.lat(), event.latLng.lng());
            self.setMarkerPosition(params.mapid, position);
            self.geofields_update(params.mapid, position);
          });

        }

        if (params.map_library === 'leaflet') {
          marker.on('dragend', function (e) {
            self.geofields_update(params.mapid, marker.getLatLng());
          });

          map.on('click', function (event) {
            var position = event.latlng;
            self.setMarkerPosition(params.mapid, position);
            self.geofields_update(params.mapid, position);
          });

        }

        // Events on Lat field change.
        $('#' + self.map_data[params.mapid].latid).on('change', function (e) {
          self.geofield_onchange(params.mapid);
        }).keydown(function (e) {
          if (e.which === 13) {
            e.preventDefault();
            self.geofield_onchange(params.mapid);
          }
        });

        // Events on Lon field change.
        $('#' + self.map_data[params.mapid].lngid).on('change', function (e) {
          self.geofield_onchange(params.mapid);
        }).keydown(function (e) {
          if (e.which === 13) {
            e.preventDefault();
            self.geofield_onchange(params.mapid);
          }
        });

        // Set default search field value (just to the first geofield_map).
        if (params.mapid === self.firstMapId && self.map_data[params.mapid].search && params.geoaddress_field_id !== null && !!self.map_data[params.mapid].geoaddress_field.val()) {
          // Copy from the geoaddress_field.val
          self.map_data[params.mapid].search.val(self.map_data[params.mapid].geoaddress_field.val());
        }
        else if (self.map_data[params.mapid].search) {
          // Sets as reverse geocode from the Geofield.
          self.reverse_geocode(params.mapid, position);
        }

      }
    }
  };

})(jQuery, Drupal, drupalSettings);
