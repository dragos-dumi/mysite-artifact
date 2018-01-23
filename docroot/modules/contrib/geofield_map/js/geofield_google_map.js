(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.geofieldGoogleMap = {
    attach: function (context, settings) {
      Drupal.geoField = Drupal.geoField || {};
      Drupal.geoField.maps = Drupal.geoField.maps || {};


      if (drupalSettings['geofield_google_map']) {
        $(context).find('.geofield-google-map').once('geofield-processed').each(function (index, element) {
          var mapid = $(element).attr('id');

          // Check if the Map container really exists and hasn't been yet initialized.
          if (drupalSettings['geofield_google_map'][mapid] && !Drupal.geoFieldMap.map_data[mapid]) {

            var map_settings = drupalSettings['geofield_google_map'][mapid]['map_settings'];
            var data = drupalSettings['geofield_google_map'][mapid]['data'];

            // Set the map_data[mapid] settings.
            Drupal.geoFieldMap.map_data[mapid] = map_settings;

            // Load before the Gmap Library, if needed.
            Drupal.geoFieldMap.loadGoogle(mapid, map_settings.gmap_api_key, function () {
              Drupal.geoFieldMap.map_initialize(mapid, map_settings, data);
            });
          }
        });
      }
    }
  };

  Drupal.geoFieldMap = {

    map_start: {
      center: {lat: 41.85, lng: -87.65},
      zoom: 18,
    },

    map_data: {},

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
    loadGoogle: function (mapid, gmap_api_key, callback) {
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
        if (typeof gmap_api_key !== 'undefined' && gmap_api_key !== null) {
          scriptPath += '&key=' + gmap_api_key;
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

    place_feature: function(feature, icon_image, mapid) {
      var self = this;

      // Define the OverlappingMarkerSpiderfier flag.
      var oms = self.map_data[mapid].oms ? self.map_data[mapid].oms : null;

      // Set the personalized Icon Image, if set.
      if (feature.setIcon && icon_image && icon_image.length > 0) {

        function checkImage(imageSrc, setIcon, logError) {
          var img = new Image();
          img.src = imageSrc;
          img.onload = setIcon;
          img.onerror = logError;
        }

        checkImage(icon_image, function(){
            feature.setIcon(icon_image);
          },
          function(){
            console.log("Geofield Gmap: The Icon Image doesn't exist at the set path");
          });
      }

      var map = self.map_data[mapid].map;
      if (oms) {
        self.map_data[mapid].oms.addMarker(feature);
      }
      else {
        feature.setMap(map);
      }
      self.map_data[mapid].markers.push(feature);

      if (feature.getPosition) {
        self.map_data[mapid].map_bounds.extend(feature.getPosition());
      } else {
        var path = feature.getPath();
        path.forEach(function(element) {
          self.map_data[mapid].map_bounds.extend(element);
        });

      }
      // Check for eventual simple or OverlappingMarkerSpiderfier click Listener
      var clickListener = oms ? 'spider_click' : 'click';
      google.maps.event.addListener(feature, clickListener, function() {
        self.infowindow_open(mapid, feature);
      });
    },

    // Closes and open the Map Infowindow at the input feature.
    infowindow_open: function (mapid, feature) {
      var self = this;
      var map = self.map_data[mapid].map;
      var properties = feature.get('geojsonProperties');
      if (feature.setTitle && properties && properties.title) {
        feature.setTitle(properties.title);
      }
      map.infowindow.close();
      if (properties.description) {
        map.infowindow.setContent(properties.description);
        setTimeout(function () {
          map.infowindow.open(map, feature);
        }, 200);
      }
    },

    // Init Geofield Google Map and its functions.
    map_initialize: function (mapid, map_settings, data) {
      var self = this;
      $.noConflict();

      // Checking to see if google variable exists. We need this b/c views breaks this sometimes. Probably
      // an AJAX/external javascript bug in core or something.
      if (typeof google !== 'undefined' && typeof google.maps.ZoomControlStyle !== 'undefined' && data !== undefined) {

        var mapOptions = {
          center: map_settings.map_center ? new google.maps.LatLng(map_settings.map_center.lat, map_settings.map_center.lon) : new google.maps.LatLng(42, 12.5),
          zoom: map_settings.map_zoom_and_pan.zoom.initial ? parseInt(map_settings.map_zoom_and_pan.zoom.initial) : 8,
          minZoom: map_settings.map_zoom_and_pan.zoom.min ? parseInt(map_settings.map_zoom_and_pan.zoom.min) : 1,
          maxZoom: map_settings.map_zoom_and_pan.zoom.max ? parseInt(map_settings.map_zoom_and_pan.zoom.max) : 20,
          scrollwheel: !!map_settings.map_zoom_and_pan.scrollwheel,
          draggable: !!map_settings.map_zoom_and_pan.draggable,
          mapTypeId: map_settings.map_controls.map_type_id ? map_settings.map_controls.map_type_id : 'roadmap',
        };

        if(!!map_settings.map_controls.disable_default_ui) {
          mapOptions.disableDefaultUI = map_settings.map_controls.disable_default_ui;
        } else {

          // Implement Custom Style Map, if Set.
          if (map_settings.custom_style_map && map_settings.custom_style_map.custom_style_control && map_settings.custom_style_map.custom_style_name.length > 0 && map_settings.custom_style_map.custom_style_options.length > 0) {
            var customMapStyleName = map_settings.custom_style_map.custom_style_name;
            var customMapStyle = JSON.parse(map_settings.custom_style_map.custom_style_options);
            var styledMapType = new google.maps.StyledMapType(customMapStyle, {name: customMapStyleName});
            map_settings.map_controls.map_type_control_options_type_ids.push('custom_styled_map');
          }

          mapOptions.zoomControl = !!map_settings.map_controls.zoom_control;
          mapOptions.mapTypeControl = !!map_settings.map_controls.map_type_control;
          mapOptions.mapTypeControlOptions = {
            mapTypeIds: map_settings.map_controls.map_type_control_options_type_ids ? map_settings.map_controls.map_type_control_options_type_ids : ['roadmap', 'satellite', 'hybrid'],
            position: google.maps.ControlPosition.TOP_LEFT,
          };
          mapOptions.scaleControl = !!map_settings.map_controls.scale_control;
          mapOptions.streetViewControl = !!map_settings.map_controls.street_view_control;
          mapOptions.fullscreenControl = !!map_settings.map_controls.fullscreen_control;
        }

        // Add map_additional_options if any.
        if (map_settings.map_additional_options.length > 0) {
          var additionalOptions = JSON.parse(map_settings.map_additional_options) ;
          // Transforms additionalOptions "true", "false" values into true & false.
          for (var prop in additionalOptions) {
            if (additionalOptions.hasOwnProperty(prop)) {
              if (additionalOptions[prop] === 'true') {
                additionalOptions[prop] = true;
              }
              if (additionalOptions[prop] === 'false') {
                additionalOptions[prop] = false;
              }
            }
          }
          // Merge mapOptions with additionalOptions.
          $.extend(mapOptions, additionalOptions);
        }

        // Define the Geofield Google Map.
        var map = new google.maps.Map(document.getElementById(mapid), mapOptions);

        // Add the Map Reset Control, if set.
        if (map_settings.map_zoom_and_pan.map_reset) {
          var mapResetControlPosition = 'TOP_RIGHT';

          // Create the DIV to hold the control and call the mapResetControl()
          // constructor passing in this DIV.
          var mapResetControlDiv = document.createElement('div');
          var mapResetControl = new self.map_reset_control(mapResetControlDiv, mapid);
          mapResetControlDiv.index = 1;
          map.controls[google.maps.ControlPosition[mapResetControlPosition]].push(mapResetControlDiv);
        }

        // If defined a Custom Map Style, associate the styled map with custom_styled_map MapTypeId and set it to display.
        if (styledMapType) {
          map.mapTypes.set('custom_styled_map', styledMapType);
          // Set Custom Map Style to Default, if requested.
          if (map_settings.custom_style_map && map_settings.custom_style_map.custom_style_default) {
            map.setMapTypeId('custom_styled_map');
          }
        }

        // Define a mapid self property, so other code can interact with it.
        self.map_data[mapid].map = map;
        self.map_data[mapid].map_options = mapOptions;
        self.map_data[mapid].features = data.features;
        self.map_data[mapid].markers = [];

        // Define the MapBounds property.
        self.map_data[mapid].map_bounds = new google.maps.LatLngBounds();

        // Set the zoom force and center property for the map.
        self.map_data[mapid].zoom_force = !!map_settings.map_zoom_and_pan.zoom.force;
        self.map_data[mapid].center_force = !!map_settings.map_center.center_force;

        // Fix map issue in field_groups / details & vertical tabs
        google.maps.event.addListenerOnce(map, "idle", function () {

          // Show all map tiles when a map is shown in a vertical tab.
          $('#' + mapid).closest('div.vertical-tabs').find('.vertical-tabs__menu-item a').click(function () {
            self.map_refresh(mapid);
          });

          // Show all map tiles when a map is shown in a collapsible detail/ single tab.
          $('#' + mapid).closest('.field-group-details, .field-group-tab').find('summary').click(function () {
              self.map_refresh(mapid);
            }
          );
        });

        // Parse the Geojson data into Google Maps Locations.
        var features = data.features && data.features.length > 0 ? GeoJSON(data) : null;

        if (features && (!features.type || features.type !== 'Error')) {

          /**
           * Implement  OverlappingMarkerSpiderfier if its control set true.
           */
          if (map_settings.map_oms && map_settings.map_oms.map_oms_control && OverlappingMarkerSpiderfier) {
            var omsOptions = map_settings.map_oms.map_oms_options.length > 0 ? JSON.parse(map_settings.map_oms.map_oms_options) : {
              markersWontMove: true,
              markersWontHide: true,
              basicFormatEvents: true,
              keepSpiderfied: true
            };
            self.map_data[mapid].oms = new OverlappingMarkerSpiderfier(map, omsOptions);
          }

          map.infowindow = new google.maps.InfoWindow({
            content: ''
          });

          // Define the icon_image, if set.
          var icon_image = map_settings.map_marker_and_infowindow.icon_image_path.length > 0 ? map_settings.map_marker_and_infowindow.icon_image_path : null;

          if (features.setMap) {
            self.place_feature(features, icon_image, mapid);
          }
          else {
            for (var i in features) {
              if (features[i].setMap) {
                self.place_feature(features[i], icon_image, mapid);
              } else {
                for (var j in features[i]) {
                  if (features[i][j].setMap) {
                    self.place_feature(features[i][j], icon_image, mapid);
                  }
                }
              }
            }
          }

          // Implement Markeclustering, if more than 1 marker on the map,
          // and the markercluster option is set to true.
          if (self.map_data[mapid].markers.length > 1 && typeof MarkerClusterer !== 'undefined' && map_settings.map_markercluster.markercluster_control) {

            var markeclusterOption = {
              imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
            };

            // Add markercluster_additional_options if any.
            if(map_settings.map_markercluster.markercluster_additional_options.length > 0) {
              var markeclusterAdditionalOptions = JSON.parse(map_settings.map_markercluster.markercluster_additional_options);
              // Merge markeclusterOption with markeclusterAdditionalOptions.
              $.extend(markeclusterOption, markeclusterAdditionalOptions);
            }

            var markerCluster = new MarkerClusterer(map, self.map_data[mapid].markers, markeclusterOption);
          }
        }

        // If the Map Initial State is defined by MapBounds.
        if (!self.map_data[mapid].map_bounds.isEmpty() && self.map_data[mapid].markers.length > 1) {
          map.fitBounds(self.map_data[mapid].map_bounds);
        }
        // else if the Map Initial State is defined by just One marker.
        else if (self.map_data[mapid].markers.length === 1) {
          map.setCenter(self.map_data[mapid].markers[0].getPosition());
          map.setZoom(mapOptions.zoom);
        }

        // At the beginning (once) ...
        google.maps.event.addListenerOnce(map, 'idle', function() {

          // Open the Feature infowindow, is so set.
          if (self.map_data[mapid].map_marker_and_infowindow.force_open && parseInt(self.map_data[mapid].map_marker_and_infowindow.force_open) === 1) {
            map.setCenter(features[0].getPosition());
            self.infowindow_open(mapid, features[0]);
          }

          // Check if the center and the zoom has to be forced.
          self.map_check_force_state(mapid);
          // Set the map start state.
          self.map_set_start_state(mapid, map.getCenter(), map.getZoom());
        });

        // Update map initial state after everything is settled.
        google.maps.event.addListener(map, 'idle', function() {
          self.map_data[mapid].map_center = map.getCenter();
          self.map_data[mapid].map_zoom = map.getZoom();
        });

        // Triggers Map resize listener on Window resize
        google.maps.event.addDomListener(window, "resize", function() {
          google.maps.event.trigger(map, "resize");
        });

        // Ensure map marker stays center on map resize
        google.maps.event.addDomListener(map, "resize", function() {
          map.setCenter(self.map_data[mapid].map_center);
        });

      }
    },
    map_check_force_state: function (mapid) {
      var self = this;
      if (self.map_data[mapid].center_force) {
        self.map_data[mapid].map.setCenter(self.map_data[mapid].map_options.center);
      }
      if (self.map_data[mapid].zoom_force) {
        self.map_data[mapid].map.setZoom(self.map_data[mapid].map_options.zoom);
      }
    },
    map_set_start_state: function (mapid, center, zoom) {
      var self = this;
      self.map_data[mapid].map_start_center = center;
      self.map_data[mapid].map_start_zoom = zoom;
    },
    map_reset_control: function (controlDiv, mapid) {
      // Set CSS for the control border.
      var controlUI = document.createElement('div');
      controlUI.style.backgroundColor = '#fff';
      controlUI.style.border = '2px solid #fff';
      controlUI.style.borderRadius = '3px';
      controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
      controlUI.style.cursor = 'pointer';
      controlUI.style.margin = '6px';
      controlUI.style.textAlign = 'center';
      controlUI.title = Drupal.t('Click to reset the map to its initial state');
      controlDiv.appendChild(controlUI);

      // Set CSS for the control interior.
      var controlText = document.createElement('div');
      controlText.style.color = 'rgb(25,25,25)';
      controlText.style.fontSize = '1.1em';
      controlText.style.lineHeight = '28px';
      controlText.style.paddingLeft = '5px';
      controlText.style.paddingRight = '5px';
      controlText.innerHTML = Drupal.t('Reset Map');
      controlUI.appendChild(controlText);

      // Setup the click event listeners: simply set the map to Chicago.
      controlUI.addEventListener('click', function() {
        Drupal.geoFieldMap.map_data[mapid].map.setCenter(Drupal.geoFieldMap.map_data[mapid].map_start_center);
        Drupal.geoFieldMap.map_data[mapid].map.setZoom(Drupal.geoFieldMap.map_data[mapid].map_start_zoom);
      });
    }

  };

})(jQuery, Drupal, drupalSettings);
