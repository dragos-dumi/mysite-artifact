<?php

namespace Drupal\geofield_map\Plugin\Field\FieldWidget;

use Drupal\geofield_map\GeofieldMapFieldTrait;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldLatLonWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\geofield\WktGeneratorInterface;

/**
 * Plugin implementation of the 'geofield_map' widget.
 *
 * @FieldWidget(
 *   id = "geofield_map",
 *   label = @Translation("Geofield Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldMapWidget extends GeofieldLatLonWidget implements ContainerFactoryPluginInterface {

  use GeofieldMapFieldTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The Renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The EntityField Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The WKT format Generator service.
   *
   * @var \Drupal\geofield\WktGeneratorInterface
   */
  protected $wktGenerator;

  /**
   * Lat Lon widget components.
   *
   * @var array
   */
  public $components = ['lon', 'lat'];

  /**
   * Leaflet Map Tile Layers.
   *
   * Free Leaflet Tile Layers from here:
   * http://leaflet-extras.github.io/leaflet-providers/preview/index.html .
   *
   * @var array
   */
  protected $leafletTileLayers = [
    'OpenStreetMap_Mapnik' => [
      'label' => 'OpenStreetMap Mapnik',
      'url' => 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      'options' => [
        'maxZoom' => 19,
        'attribution' => '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'OpenTopoMap' => [
      'label' => 'OpenTopoMap',
      'url' => 'http://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      'options' => [
        'maxZoom' => 17,
        'attribution' => 'Map data: &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
      ],
    ],
    'OpenMapSurfer_Roads' => [
      'label' => 'OpenMapSurfer Roads',
      'url' => 'http://korona.geog.uni-heidelberg.de/tiles/roads/x={x}&y={y}&z={z}',
      'options' => [
        'maxZoom' => 20,
        'attribution' => 'Imagery from <a href="http://giscience.uni-hd.de/">GIScience Research Group @ University of Heidelberg</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'Stamen_Toner' => [
      'label' => 'Stamen Toner',
      'url' => 'http://stamen-tiles-{s}.a.ssl.fastly.net/toner/{z}/{x}/{y}.{ext}',
      'options' => [
        'minZoom' => 0,
        'maxZoom' => 20,
        'ext' => 'png',
        'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'Stamen_Watercolor' => [
      'label' => 'Stamen Watercolor',
      'url' => 'http://stamen-tiles-{s}.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.{ext}',
      'options' => [
        'minZoom' => 1,
        'maxZoom' => 16,
        'ext' => 'png',
        'subdomains' => 'abcd',
        'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'Stamen_Terrain' => [
      'label' => 'Stamen Terrain',
      'url' => 'http://stamen-tiles-{s}.a.ssl.fastly.net/terrain/{z}/{x}/{y}.{ext}',
      'options' => [
        'minZoom' => 4,
        'maxZoom' => 18,
        'ext' => 'png',
        'bounds' => [[22, -132], [70, -56]],
        'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
  ];

  /**
   * Leaflet Map Tile Layers Options.
   *
   * @var array
   */
  protected $leafletTileLayersOptions;

  /**
   * GeofieldMapWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The Translation service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Entity Field Manager.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\geofield\WktGeneratorInterface $wkt_generator
   *   The WKT format Generator service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $string_translation,
    RendererInterface $renderer,
    EntityFieldManagerInterface $entity_field_manager,
    LinkGeneratorInterface $link_generator,
    WktGeneratorInterface $wkt_generator
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->config = $config_factory;
    $this->renderer = $renderer;
    $this->entityFieldManager = $entity_field_manager;
    $this->link = $link_generator;
    $this->wktGenerator = $wkt_generator;

    foreach ($this->leafletTileLayers as $k => $tile_layer) {
      $this->leafletTileLayersOptions[$k] = $tile_layer['label'];
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('string_translation'),
      $container->get('renderer'),
      $container->get('entity_field.manager'),
      $container->get('link_generator'),
      $container->get('geofield.wkt_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'default_value' => [
        'lat' => '0',
        'lon' => '0',
      ],
      'map_library' => 'gmap',
      'map_google_api_key' => '',
      'map_dimensions' => [
        'width' => '100%',
        'height' => '450px',
      ],
      'map_type_google' => 'roadmap',
      'map_type_leaflet' => 'OpenStreetMap_Mapnik',
      'map_type_selector' => TRUE,
      'zoom_level' => 5,
      'zoom' => [
        'start' => 6,
        'focus' => 12,
        'min' => 1,
        'max' => 22,
      ],
      'click_to_find_marker' => FALSE,
      'click_to_place_marker' => FALSE,
      'geoaddress_field' => [
        'field' => '0',
        'hidden' => FALSE,
        'disabled' => TRUE,
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $default_settings = self::defaultSettings();

    $elements = [];

    // Attach Geofield Map Library.
    $elements['#attached']['library'] = [
      'geofield_map/geofield_map_general',
      'geofield_map/geofield_map_widget',
    ];

    $elements['#tree'] = TRUE;

    $elements['default_value'] = [
      'lat' => [
        '#type' => 'value',
        '#value' => $this->getSetting('default_value')['lat'],
      ],
      'lon' => [
        '#type' => 'value',
        '#value' => $this->getSetting('default_value')['lon'],
      ],
    ];

    $gmap_api_key = $this->getGmapApiKey();

    // Define the Google Maps API Key value message markup.
    if (!empty($gmap_api_key)) {
      $map_google_api_key_value = $this->t('<strong>Gmap Api Key:</strong> @gmaps_api_key_link<br><div class="description">A valid Gmap Api Key is needed anyway for the Widget Geocode and ReverseGeocode functionalities (provided by the Google Map Geocoder)</div>', [
        '@gmaps_api_key_link' => $this->link->generate($gmap_api_key, Url::fromRoute('geofield_map.settings', [], [
          'query' => [
            'destination' => Url::fromRoute('<current>')
              ->toString(),
          ],
        ])),
      ]);
    }
    else {
      $map_google_api_key_value = t("<span class='geofield-map-warning'>Gmap Api Key missing<br>The Widget Geocode and ReverseGeocode functionalities won't be available.</span> @settings_page_link", [
        '@settings_page_link' => $this->link->generate(t('Set it in the Geofield Map Configuration Page'), Url::fromRoute('geofield_map.settings', [], [
          'query' => [
            'destination' => Url::fromRoute('<current>')
              ->toString(),
          ],
        ])),
      ]);
    }

    $elements['map_google_api_key'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $map_google_api_key_value,
    ];

    $elements['map_library'] = [
      '#type' => 'select',
      '#title' => $this->t('Map Library'),
      '#default_value' => $this->getSetting('map_library'),
      '#options' => [
        'gmap' => $this->t('Google Maps'),
        'leaflet' => $this->t('Leaflet js'),
      ],
    ];

    $elements['map_type_google'] = [
      '#type' => 'select',
      '#title' => $this->t('Map type'),
      '#default_value' => $this->getSetting('map_type_google'),
      '#options' => $this->gMapTypesOptions,
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_library]"]' => ['value' => 'gmap'],
        ],
      ],
    ];

    $elements['map_type_leaflet'] = [
      '#type' => 'select',
      '#title' => $this->t('Map type'),
      '#default_value' => $this->getSetting('map_type_leaflet'),
      '#options' => $this->leafletTileLayersOptions,
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_library]"]' => ['value' => 'leaflet'],
        ],
      ],
    ];

    $elements['map_type_selector'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Provide a Map type Selector on the Map'),
      '#description' => $this->t('If checked, the user will be able to change Map Type throughout the selector.'),
      '#default_value' => $this->getSetting('map_type_selector'),
    ];

    $elements['map_dimensions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Dimensions'),
    ];
    $elements['map_dimensions']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map width'),
      '#default_value' => $this->getSetting('map_dimensions')['width'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default width of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];
    $elements['map_dimensions']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map height'),
      '#default_value' => $this->getSetting('map_dimensions')['height'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default height of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];

    $elements['zoom'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Zoom Settings'),
    ];
    $elements['zoom']['start'] = [
      '#type' => 'number',
      '#min' => $this->getSetting('zoom')['min'],
      '#max' => $this->getSetting('zoom')['max'],
      '#title' => $this->t('Start Zoom level'),
      '#description' => $this->t('The initial Zoom level for an empty Geofield.'),
      '#default_value' => $this->getSetting('zoom')['start'],
      '#element_validate' => [[get_class($this), 'zoomLevelValidate']],
    ];
    $elements['zoom']['focus'] = [
      '#type' => 'number',
      '#min' => $this->getSetting('zoom')['min'],
      '#max' => $this->getSetting('zoom')['max'],
      '#title' => $this->t('Focus Zoom level'),
      '#description' => $this->t('The Zoom level for an assigned Geofield or for Geocoding operations results.'),
      '#default_value' => $this->getSetting('zoom')['focus'],
      '#element_validate' => [[get_class($this), 'zoomLevelValidate']],
    ];
    $elements['zoom']['min'] = [
      '#type' => 'number',
      '#min' => $default_settings['zoom']['min'],
      '#max' => $default_settings['zoom']['max'],
      '#title' => $this->t('Min Zoom level'),
      '#description' => $this->t('The Minimum Zoom level for the Map.'),
      '#default_value' => $this->getSetting('zoom')['min'],
    ];
    $elements['zoom']['max'] = [
      '#type' => 'number',
      '#min' => $default_settings['zoom']['min'],
      '#max' => $default_settings['zoom']['max'],
      '#title' => $this->t('Max Zoom level'),
      '#description' => $this->t('The Maximum Zoom level for the Map.'),
      '#default_value' => $this->getSetting('zoom')['max'],
      '#element_validate' => [[get_class($this), 'maxZoomLevelValidate']],
    ];

    $elements['click_to_find_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Click to Find marker'),
      '#description' => $this->t('Provides a button to recenter the map on the marker location.'),
      '#default_value' => $this->getSetting('click_to_find_marker'),
    ];

    $elements['click_to_place_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Click to place marker'),
      '#description' => $this->t('Provides a button to place the marker in the center location.'),
      '#default_value' => $this->getSetting('click_to_place_marker'),
    ];

    $fields_list = array_merge_recursive(
      $this->entityFieldManager->getFieldMapByFieldType('string_long'),
      $this->entityFieldManager->getFieldMapByFieldType('string')
    );

    $string_fields_options = [
      '0' => $this->t('- Any -'),
    ];

    // Filter out the not acceptable values from the options.
    foreach ($fields_list[$form['#entity_type']] as $k => $field) {
      if (in_array(
          $form['#bundle'], $field['bundles']) &&
        !in_array($k, [
          'revision_log',
          'behavior_settings',
          'parent_id',
          'parent_type',
          'parent_field_name',
        ])) {
        $string_fields_options[$k] = $k;
      }
    }

    $elements['geoaddress_field'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geoaddressed Field'),
      '#description' => $this->t('If a not null Google Maps API Key is set, it is possible to choose the Entity Title, or a "string" type field (among the content type ones), to sync and populate with the Search / Reverse Geocoded Address.<br><strong> Note: In case of a multivalue Geofield, this is run just from the first Geofield Map</strong>'),
      '#states' => [
        'invisible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_google_api_key]"]' => ['value' => ''],
        ],
      ],
    ];
    $elements['geoaddress_field']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose an existing field where to store the Searched / Reverse Geocoded Address'),
      '#description' => $this->t('Choose among the title and the text fields of this entity type, if available'),
      '#options' => $string_fields_options,
      '#default_value' => $this->getSetting('geoaddress_field')['field'],
    ];
    $elements['geoaddress_field']['hidden'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Hide</strong> this field in the Content Edit Form'),
      '#description' => $this->t('If checked, the selected Geoaddress Field will be Hidden to the user in the edit form, </br>and totally managed by the Geofield Reverse Geocode'),
      '#default_value' => $this->getSetting('geoaddress_field')['hidden'],
      '#states' => [
        'invisible' => [
          [':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geoaddress_field][field]"]' => ['value' => 'title']],
          'or',
          [':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geoaddress_field][field]"]' => ['value' => '0']],
        ],
      ],
    ];
    $elements['geoaddress_field']['disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Disable</strong> this field in the Content Edit Form'),
      '#description' => $this->t('If checked, the selected Geoaddress Field will be Disabled to the user in the edit form, </br>and totally managed by the Geofield Reverse Geocode'),
      '#default_value' => $this->getSetting('geoaddress_field')['disabled'],
      '#states' => [
        'invisible' => [
          [':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geoaddress_field][hidden]"]' => ['checked' => TRUE]],
          'or',
          [':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geoaddress_field][field]"]' => ['value' => '0']],
        ],
      ],
    ];

    return $elements + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $gmap_api_key = $this->getGmapApiKey();

    $map_library = [
      '#markup' => $this->t('Map Library: @state', ['@state' => 'gmap' == $this->getSetting('map_library') ? 'Google Maps' : 'Leaflet Js']),
    ];

    $map_type = [
      '#markup' => $this->t('Map Type: @state', ['@state' => 'leaflet' == $this->getSetting('map_library') ? $this->getSetting('map_type_leaflet') : $this->getSetting('map_type_google')]),
    ];

    // Define the Google Maps API Key value message string.
    if (!empty($gmap_api_key)) {
      $state = $this->link->generate($gmap_api_key, Url::fromRoute('geofield_map.settings', [], [
        'query' => [
          'destination' => Url::fromRoute('<current>')
            ->toString(),
        ],
      ]));
    }
    else {
      $state = t("<span class='geofield-map-warning'>Gmap Api Key missing<br>Geocode functionalities not available.</span> @settings_page_link", [
        '@settings_page_link' => $this->link->generate(t('Set it in the Geofield Map Configuration Page'), Url::fromRoute('geofield_map.settings', [], [
          'query' => [
            'destination' => Url::fromRoute('<current>')
              ->toString(),
          ],
        ])),
      ]);
    }

    $map_gmap_api_key = [
      '#markup' => $this->t('Google Maps API Key: @state', [
        '@state' => $state,
      ]),
    ];

    $map_type_selector = [
      '#markup' => $this->t('Map Type Selector: @state', ['@state' => $this->getSetting('map_type_selector') ? $this->t('enabled') : $this->t('disabled')]),
    ];

    $map_dimensions = [
      '#markup' => $this->t('Map Dimensions -'),
    ];

    $map_dimensions['#markup'] .= ' ' . $this->t('Width: @state;', ['@state' => $this->getSetting('map_dimensions')['width']]);
    $map_dimensions['#markup'] .= ' ' . $this->t('Height: @state;', ['@state' => $this->getSetting('map_dimensions')['height']]);

    $map_zoom_levels = [
      '#markup' => $this->t('Zoom Levels -'),
    ];

    $map_zoom_levels['#markup'] .= ' ' . $this->t('Start: @state;', ['@state' => $this->getSetting('zoom')['start']]);
    $map_zoom_levels['#markup'] .= ' ' . $this->t('Focus: @state;', ['@state' => $this->getSetting('zoom')['focus']]);
    $map_zoom_levels['#markup'] .= ' ' . $this->t('Min: @state;', ['@state' => $this->getSetting('zoom')['min']]);
    $map_zoom_levels['#markup'] .= ' ' . $this->t('Max: @state;', ['@state' => $this->getSetting('zoom')['max']]);

    $html5 = [
      '#markup' => $this->t('HTML5 Geolocation button: @state', ['@state' => $this->getSetting('html5_geolocation') ? $this->t('enabled') : $this->t('disabled')]),
    ];

    $map_center = [
      '#markup' => $this->t('Click to find marker: @state', ['@state' => $this->getSetting('click_to_find_marker') ? $this->t('enabled') : $this->t('disabled')]),
    ];

    $marker_center = [
      '#markup' => $this->t('Click to place marker: @state', ['@state' => $this->getSetting('click_to_place_marker') ? $this->t('enabled') : $this->t('disabled')]),
    ];

    $geoaddress_field_field = [
      '#markup' => $this->t('Geoaddress Field: @state', ['@state' => ('0' != $this->getSetting('geoaddress_field')['field']) ? $this->getSetting('geoaddress_field')['field'] : $this->t('- any -')]),
    ];

    $geoaddress_field_hidden = [
      '#markup' => ('0' != $this->getSetting('geoaddress_field')['field']) ? $this->t('Geoaddress Field Hidden: @state', ['@state' => $this->getSetting('geoaddress_field')['hidden']]) : '',
    ];

    $geoaddress_field_disabled = [
      '#markup' => ('0' != $this->getSetting('geoaddress_field')['field']) ? $this->t('Geoaddress Field Disabled: @state', ['@state' => $this->getSetting('geoaddress_field')['disabled']]) : '',
    ];

    $summary = [
      'map_library' => $map_library,
      'map_type' => $map_type,
      'map_gmap_api_key' => $map_gmap_api_key,
      'map_type_selector' => $map_type_selector,
      'map_dimensions' => $map_dimensions,
      'map_zoom_levels' => $map_zoom_levels,
      'html5' => $html5,
      'map_center' => $map_center,
      'marker_center' => $marker_center,
      'field' => $geoaddress_field_field,
      'hidden' => $geoaddress_field_hidden,
      'disabled' => $geoaddress_field_disabled,
    ];

    // Attach Geofield Map Library.
    $summary['library'] = [
      '#attached' => [
        'library' => [
          'geofield_map/geofield_map_general',
        ],
      ],
    ];

    return $summary;
  }

  /**
   * Implements \Drupal\field\Plugin\Type\Widget\WidgetInterface::formElement().
   *
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $gmap_api_key = $this->getGmapApiKey();

    $latlon_value = [];

    foreach ($this->components as $component) {
      $latlon_value[$component] = isset($items[$delta]->{$component}) ? floatval($items[$delta]->{$component}) : $this->getSetting('default_value')[$component];
    }

    $element += [
      '#type' => 'geofield_map',
      '#default_value' => $latlon_value,
      '#geolocation' => $this->getSetting('html5_geolocation'),
      '#geofield_map_geolocation_override' => $this->getSetting('html5_geolocation'),
      '#map_library' => $this->getSetting('map_library'),
      '#map_type' => 'leaflet' === $this->getSetting('map_library') ? $this->getSetting('map_type_leaflet') : $this->getSetting('map_type_google'),
      '#map_type_selector' => $this->getSetting('map_type_selector'),
      '#map_types_google' => $this->gMapTypesOptions,
      '#map_types_leaflet' => $this->leafletTileLayers,
      '#map_dimensions' => $this->getSetting('map_dimensions'),
      '#zoom' => $this->getSetting('zoom'),
      '#click_to_find_marker' => $this->getSetting('click_to_find_marker'),
      '#click_to_place_marker' => $this->getSetting('click_to_place_marker'),
      '#geoaddress_field' => $this->getSetting('geoaddress_field'),
      '#error_label' => !empty($element['#title']) ? $element['#title'] : $this->fieldDefinition->getLabel(),
      '#gmap_api_key' => $gmap_api_key,
    ];

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      foreach ($this->components as $component) {
        if (empty($value['value'][$component]) || !is_numeric($value['value'][$component])) {
          $values[$delta]['value'] = '';
          continue 2;
        }
      }
      $components = $value['value'];
      $values[$delta]['value'] = $this->wktGenerator->WktBuildPoint([$components['lon'], $components['lat']]);
    }

    return $values;
  }

}
