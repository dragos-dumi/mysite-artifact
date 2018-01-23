<?php

namespace Drupal\geofield_map;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GeofieldMapFieldTrait.
 *
 * Provide common functions for Geofield Map fields.
 *
 * @package Drupal\geofield_map
 */
trait GeofieldMapFieldTrait {

  /**
   * Google Map Types Options.
   *
   * @var array
   */
  protected $gMapTypesOptions = [
    'roadmap' => 'Roadmap',
    'satellite' => 'Satellite',
    'hybrid' => 'Hybrid',
    'terrain' => 'Terrain',
  ];

  protected $customMapStylePlaceholder = '[{"elementType":"geometry","stylers":[{"color":"#1d2c4d"}]},{"elementType":"labels.text.fill","stylers":[{"color":"#8ec3b9"}]},{"elementType":"labels.text.stroke","stylers":[{"color":"#1a3646"}]},{"featureType":"administrative.country","elementType":"geometry.stroke","stylers":[{"color":"#4b6878"}]},{"featureType":"administrative.province","elementType":"geometry.stroke","stylers":[{"color":"#4b6878"}]},{"featureType":"water","elementType":"geometry","stylers":[{"color":"#0e1626"}]},{"featureType":"water","elementType":"labels.text.fill","stylers":[{"color":"#4e6d70"}]}]';

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface $this->link
   */

  /**
   * Get the GMap Api Key from the geofield_map settings/configuration.
   *
   * @return string
   *   The GMap Api Key
   */
  private function getGmapApiKey() {
    /* @var \Drupal\Core\Config\ConfigFactoryInterface $config */
    $config = $this->config;
    $geofield_map_settings = $config->getEditable('geofield_map.settings');
    $gmap_api_key = $geofield_map_settings->get('gmap_api_key');

    // In the first release of Geofield_Map the google_api_key was stored in
    // the specific Field Widget settings.
    // So we try and copy into the geofield_map.settings config, in the case.
    if (method_exists(get_class($this), 'getSetting') && !empty($this->getSetting('map_google_api_key')) && empty($gmap_api_key)) {
      $gmap_api_key = $this->getSetting('map_google_api_key');
      $geofield_map_settings->set('gmap_api_key', $gmap_api_key)->save();
    }
    return $gmap_api_key;
  }

  /**
   * Get the Default Settings.
   *
   * @return array
   *   The default settings.
   */
  public static function getDefaultSettings() {
    return [
      'gmap_api_key' => '',
      'map_dimensions' => [
        'width' => '100%',
        'height' => '450px',
      ],
      'map_empty' => [
        'empty_behaviour' => '0',
        'empty_message' => t('No Geofield Value entered for this field'),
      ],
      'map_center' => [
        'lat' => '42',
        'lon' => '12.5',
        'center_force' => 0,
      ],
      'map_zoom_and_pan' => [
        'zoom' => [
          'initial' => 6,
          'force' => 0,
          'min' => 1,
          'max' => 22,
        ],
        'scrollwheel' => 1,
        'draggable' => 1,
        'map_reset' => 0,
      ],
      'map_controls' => [
        'disable_default_ui' => 0,
        'zoom_control' => 1,
        'map_type_id' => 'roadmap',
        'map_type_control' => 1,
        'map_type_control_options_type_ids' => [
          'roadmap' => 'roadmap',
          'satellite' => 'satellite',
          'hybrid' => 'hybrid',
          'terrain' => 'terrain',
        ],
        'scale_control' => 1,
        'street_view_control' => 1,
        'fullscreen_control' => 1,
      ],
      'map_marker_and_infowindow' => [
        'icon_image_path' => '',
        'infowindow_field' => 'title',
        'multivalue_split' => 0,
        'force_open' => 0,
      ],
      'map_oms' => [
        'map_oms_control' => 1,
        'map_oms_options' => '{"markersWontMove": "true", "markersWontHide": "true", "basicFormatEvents": "true", "nearbyDistance": 3}',
      ],
      'map_additional_options' => '',
      'custom_style_map' => [
        'custom_style_control' => 0,
        'custom_style_name' => '',
        'custom_style_options' => '',
        'custom_style_default' => 0,
      ],
      'map_markercluster' => [
        'markercluster_control' => 0,
        'markercluster_additional_options' => '',
      ],
    ];
  }

  /**
   * Generate the Google Map Settings Form.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $settings
   *   Form settings.
   * @param array $default_settings
   *   Default settings.
   *
   * @return array
   *   The GMap Settings Form*/
  public function generateGmapSettingsForm(array $form, FormStateInterface $form_state, array $settings, array $default_settings) {

    // If it is a Field Formatter, then get the field definition.
    /* @var \Drupal\Core\Field\FieldDefinitionInterface|NULL $field_definition */
    $field_definition = property_exists(get_class($this), 'fieldDefinition') ? $this->fieldDefinition : NULL;

    // Get the configurations of possible entity fields.
    $fields_configurations = $this->entityFieldManager->getFieldStorageDefinitions('node');

    $elements = [];

    // Attach Geofield Map Library.
    $elements['#attached']['library'] = [
      'geofield_map/geofield_map_general',
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
      $map_google_api_key_value = $this->t("<span class='gmap-apikey-missing'>Gmap Api Key missing<br>Some Google Map functionality may not be available.</span>@settings_page_link", [
        '@settings_page_link' => $this->link->generate($this->t('Set it in the Geofield Map Configuration Page'), Url::fromRoute('geofield_map.settings', [], [
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

    $elements['map_dimensions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Dimensions'),
    ];

    $elements['map_dimensions']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map width'),
      '#default_value' => $settings['map_dimensions']['width'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default width of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];
    $elements['map_dimensions']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map height'),
      '#default_value' => $settings['map_dimensions']['height'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default height of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];

    $elements['gmaps_api_link_markup'] = [
      '#markup' => $this->t('The following settings comply with the @gmaps_api_link.', [
        '@gmaps_api_link' => $this->link->generate($this->t('Google Maps JavaScript API Library'), Url::fromUri('https://developers.google.com/maps/documentation/javascript', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];

    $elements['map_empty'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Which behaviour for the empty map?'),
      '#description' => $this->t('If there are no entries on the map, what should be the output of field?'),
    ];

    if (isset($field_definition)) {
      $elements['map_empty']['empty_behaviour'] = [
        '#type' => 'select',
        '#title' => $this->t('Behaviour'),
        '#default_value' => $settings['map_empty']['empty_behaviour'],
        '#options' => $this->emptyMapOptions,
      ];
      $elements['map_empty']['empty_message'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Empty Map Message'),
        '#description' => $this->t('The message that should be rendered instead on an empty map.'),
        '#default_value' => $settings['map_empty']['empty_message'],
        '#states' => [
          'visible' => [
            ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_empty][empty_behaviour]"]' => ['value' => '1'],
          ],
        ],
      ];
    }
    else {
      $elements['map_empty']['empty_behaviour'] = [
        '#type' => 'select',
        '#title' => $this->t('Behaviour'),
        '#default_value' => $settings['map_empty']['empty_behaviour'],
        '#options' => $this->emptyMapOptions,
      ];
    }

    $elements['map_center'] = [
      '#type' => 'geofield_latlon',
      '#title' => $this->t('Default Center'),
      '#default_value' => $settings['map_center'],
      '#size' => 25,
      '#description' => $this->t('If there are no entries on the map, where should the map be centered?'),
      '#geolocation' => TRUE,
      'center_force' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Force the Map Center'),
        '#description' => $this->t('The Map will generally focus center on the input Geofields.<br>This option will instead force the Map Center notwithstanding the Geofield Values'),
        '#default_value' => $settings['map_center']['center_force'],
        '#return_value' => 1,
      ],
    ];

    $elements['map_zoom_and_pan'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Zoom and Pan'),
    ];
    $elements['map_zoom_and_pan']['zoom'] = [
      'initial' => [
        '#type' => 'number',
        '#min' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Start Zoom'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['initial'],
        '#description' => $this->t('The Initial Zoom level of the Google Map.'),
        '#element_validate' => [[get_class($this), 'zoomLevelValidate']],
      ],
      'force' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Force the Start Zoom'),
        '#description' => $this->t('In case of multiple GeoMarkers, the Map will naturally focus zoom on the input Geofields bounds.<br>This option will instead force the Map Zoom on the input Start Zoom value'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['force'],
        '#return_value' => 1,
      ],
      'min' => [
        '#type' => 'number',
        '#min' => isset($default_settings['map_zoom_and_pan']['default']) ? $default_settings['map_zoom_and_pan']['default']['zoom']['min'] : $default_settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Min Zoom Level'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#description' => $this->t('The Minimum Zoom level for the Map.'),
      ],
      'max' => [
        '#type' => 'number',
        '#min' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => isset($default_settings['map_zoom_and_pan']['default']) ? $default_settings['map_zoom_and_pan']['default']['zoom']['max'] : $default_settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Max Zoom Level'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#description' => $this->t('The Maximum Zoom level for the Map.'),
        '#element_validate' => [[get_class($this), 'maxZoomLevelValidate']],
      ],
    ];
    $elements['map_zoom_and_pan']['scrollwheel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scrollwheel'),
      '#description' => $this->t('Enable scrollwheel zooming'),
      '#default_value' => $settings['map_zoom_and_pan']['scrollwheel'],
      '#return_value' => 1,
    ];
    $elements['map_zoom_and_pan']['draggable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draggable'),
      '#description' => $this->t('Enable dragging/panning on the map'),
      '#default_value' => $settings['map_zoom_and_pan']['draggable'],
      '#return_value' => 1,
    ];

    $elements['map_zoom_and_pan']['map_reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Map Reset Control'),
      '#description' => $this->t('This will show a "Reset Map" button to reset the Map to its initial center & zoom state'),
      '#default_value' => isset($settings['map_zoom_and_pan']['map_reset']) ? $settings['map_zoom_and_pan']['map_reset'] : FALSE,
      '#return_value' => 1,
    ];

    if (isset($field_definition)) {
      $disable_default_ui_selector = ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_controls][disable_default_ui]"]';
    }
    else {
      $disable_default_ui_selector = ':input[name="style_options[map_controls][disable_default_ui]"]';
    }

    $elements['map_controls'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Controls'),
    ];
    $elements['map_controls']['disable_default_ui'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Default UI'),
      '#description' => $this->t('This property disables any automatic UI behavior and Control from the Google Map'),
      '#default_value' => $settings['map_controls']['disable_default_ui'],
      '#return_value' => 1,
    ];
    $elements['map_controls']['zoom_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Zoom Control'),
      '#description' => $this->t('The enabled/disabled state of the Zoom control.'),
      '#default_value' => $settings['map_controls']['zoom_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['map_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Map Type'),
      '#default_value' => $settings['map_controls']['map_type_id'],
      '#options' => $this->gMapTypesOptions,
    ];
    $elements['map_controls']['map_type_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled Map Type Control'),
      '#description' => $this->t('The initial enabled/disabled state of the Map type control.'),
      '#default_value' => $settings['map_controls']['map_type_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['map_type_control_options_type_ids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('The enabled Map Types'),
      '#description' => $this->t('The Map Types that will be available in the Map Type Control.'),
      '#default_value' => $settings['map_controls']['map_type_control_options_type_ids'],
      '#options' => $this->gMapTypesOptions,
      '#return_value' => 1,
    ];

    if (isset($field_definition)) {
      $elements['map_controls']['map_type_control_options_type_ids']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_controls][map_type_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          $disable_default_ui_selector => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $elements['map_controls']['map_type_control_options_type_ids']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_controls][map_type_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          $disable_default_ui_selector => ['checked' => TRUE],

        ],
      ];
    }

    $elements['map_controls']['scale_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scale Control'),
      '#description' => $this->t('Show map scale'),
      '#default_value' => $settings['map_controls']['scale_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['street_view_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Streetview Control'),
      '#description' => $this->t('Enable the Street View functionality on the Map.'),
      '#default_value' => $settings['map_controls']['street_view_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['fullscreen_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fullscreen Control'),
      '#description' => $this->t('Enable the Fullscreen View of the Map.'),
      '#default_value' => $settings['map_controls']['fullscreen_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];

    $elements['map_marker_and_infowindow'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Marker and Infowindow'),
    ];
    $elements['map_marker_and_infowindow']['icon_image_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon Image'),
      '#size' => '120',
      '#description' => $this->t('Input the Specific Icon Image path (absolute path, or relative to the Drupal site root). If not set, or not found/loadable, the Default Google Marker will be used.'),
      '#default_value' => $settings['map_marker_and_infowindow']['icon_image_path'],
      '#placeholder' => 'modules/custom/geofield_map/images/beachflag.png',
      '#element_validate' => [[get_class($this), 'urlValidate']],
    ];

    // Check if a Field Formatter is being managed and define its possible
    // Entity Type and Bundles.
    if (isset($form['#entity_type'])) {
      $entity_type = $form['#entity_type'];
      $bundles = (!empty($form['#bundle'])) ? [$form['#bundle']] : [];
    }
    elseif (property_exists(get_class($this), 'fieldDefinition')) {
      $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
      $field_name = $this->fieldDefinition->getName();
      $fields = $this->entityFieldManager->getFieldMapByFieldType($this->fieldDefinition->getType());
      $bundles = !empty($fields['node'][$field_name]['bundles']) ? $fields['node'][$field_name]['bundles'] : [];
    }

    $multivalue_fields_states = [];

    $fields_list = array_merge_recursive(
      $this->entityFieldManager->getFieldMapByFieldType('string_long'),
      $this->entityFieldManager->getFieldMapByFieldType('string'),
      $this->entityFieldManager->getFieldMapByFieldType('text'),
      $this->entityFieldManager->getFieldMapByFieldType('text_long')
    );

    // In case it is a Field Formatter.
    if (isset($entity_type)) {
      $desc_options = [
        '0' => $this->t('- Any - No Infowindow'),
        'title' => $this->t('- Title -'),
      ];

      // Get the Cardinality set for the Formatter Field.
      $field_cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

      foreach ($fields_list[$entity_type] as $k => $field) {
        if (isset($bundles) && !empty(array_intersect($field['bundles'], $bundles)) &&
          !in_array($k, ['title', 'revision_log'])) {
          $desc_options[$k] = $k;
          /* @var \\Drupal\Core\Field\BaseFieldDefinition $fields_configurations[$k] */
          if ($field_cardinality !== 1 && $fields_configurations[$k]->getCardinality() !== 1) {
            $multivalue_fields_states[] = ['value' => $k];
          }
        }
      }

      $desc_options['#rendered_entity'] = $this->t('- Rendered @entity entity -', ['@entity' => $entity_type]);

      $info_window_source_options = $desc_options;

    }
    // Else it is a Geofield View Style Format Settings.
    else {
      $info_window_source_options = isset($settings['infowindow_content_options']) ? $settings['infowindow_content_options'] : [];

      foreach ($info_window_source_options as $k => $field) {
        /* @var \\Drupal\Core\Field\BaseFieldDefinition $fields_configurations[$k] */
        if (array_key_exists($k, $fields_configurations) && $fields_configurations[$k]->getCardinality() !== 1) {
          $multivalue_fields_states[] = ['value' => $k];
        }

        if (array_key_exists($k, $fields_configurations) && $fields_configurations[$k]->getCardinality() !== 1) {
          $multivalue_fields_states[] = ['value' => $k];
        }

        // Remove the fields options that are not string/text type fields.
        if (isset($this->entityType) && substr($k, 0, 5) == 'field' && !array_key_exists($k, $fields_list[$k])) {
          unset($info_window_source_options[$k]);
        }

      }

    }

    if (!empty($info_window_source_options)) {
      $elements['map_marker_and_infowindow']['infowindow_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Marker Infowindow Content from'),
        '#description' => $this->t('Choose an existing string/text type field from which populate the Marker Infowindow'),
        '#options' => $info_window_source_options,
        '#default_value' => $settings['map_marker_and_infowindow']['infowindow_field'],
      ];
    }

    $elements['map_marker_and_infowindow']['multivalue_split'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multivalue Field Split (<u>A Multivalue Field as been selected for the Infowindow Content)</u>'),
      '#description' => $this->t('If checked, each field value will be split into each matching infowindow, following the same progressive order<br>(the first value of the field will be used otherwise, or as fallback in case of no match)'),
      '#default_value' => !empty($settings['map_marker_and_infowindow']['multivalue_split']) ? $settings['map_marker_and_infowindow']['multivalue_split'] : 0,
      '#return_value' => 1,
    ];

    if (isset($field_definition)) {
      $elements['map_marker_and_infowindow']['multivalue_split']['#description'] = $this->t('If checked, each field value will be split into each matching infowindow / geofield, following the same progressive order<br>(the first value of the field will be used otherwise, or as fallback in case of no match)');
      $elements['map_marker_and_infowindow']['multivalue_split']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_marker_and_infowindow][infowindow_field]"]' => $multivalue_fields_states,
        ],
      ];
    }
    else {
      $elements['map_marker_and_infowindow']['multivalue_split']['#description'] = $this->t('If checked, each field value will be split into each matching infowindow /geofield , following the same progressive order<br>(The Multiple Field settings from the View Display will be used otherwise)');
      $elements['map_marker_and_infowindow']['multivalue_split']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_marker_and_infowindow][infowindow_field]"]' => $multivalue_fields_states,
        ],
      ];
    }

    if (isset($entity_type)) {
      // Get the human readable labels for the entity view modes.
      $view_mode_options = [];
      foreach ($this->entityDisplayRepository->getViewModes($entity_type) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $elements['map_marker_and_infowindow']['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode the entity will be displayed in the Infowindow.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($settings['map_marker_and_infowindow']['view_mode']) ? $settings['map_marker_and_infowindow']['view_mode'] : NULL,
        '#states' => [
          'visible' => [
            ':input[name$="[settings][map_marker_and_infowindow][infowindow_field]"]' => [
              'value' => '#rendered_entity',
            ],
          ],
        ],
      ];
    }

    if (isset($field_definition)) {
      $elements['map_marker_and_infowindow']['force_open'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Open Infowindow on Load'),
        '#description' => $this->t('If checked the Infowindow will automatically open on page load.<br><b>Note:</b> in case of multivalue Geofield, the Infowindow will be opened (and the Map centered) on the first item.'),
        '#default_value' => !empty($settings['map_marker_and_infowindow']['force_open']) ? $settings['map_marker_and_infowindow']['force_open'] : 0,
        '#return_value' => 1,
      ];
    }

    $elements['map_additional_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Map Additional Options'),
      '#description' => $this->t('<strong>These will override the above settings</strong><br>An object literal of additional map options, that comply with the Google Maps JavaScript API.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br>It is even possible to input Map Control Positions. For this use the numeric values of the google.maps.ControlPosition, otherwise the option will be passed as incomprehensible string to Google Maps API.'),
      '#default_value' => $settings['map_additional_options'],
      '#placeholder' => '{"disableDoubleClickZoom": "cooperative",
"gestureHandling": "none",
"streetViewControlOptions": {"position": 5}
}',
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    $elements['map_oms'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Overlapping Markers'),
      '#description' => $this->t('<b>Note: </b>To make this working in conjunction with the Markercluster Option (see below) a "maxZoom" property should be set in the Marker Cluster Additional Options.'),
      '#description_display' => 'before',
    ];
    $elements['map_oms']['map_oms_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Spiderfy overlapping markers'),
      '#description' => $this->t('Use the standard setup of the @overlapping_marker_spiderfier to manage Overlapping Markers located in the exact same position.', [
        '@overlapping_marker_spiderfier' => $this->link->generate(t('Overlapping Marker Spiderfier Library (for Google Maps)'), Url::fromUri('https://github.com/jawj/OverlappingMarkerSpiderfier#overlapping-marker-spiderfier-for-google-maps-api-v3', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#default_value' => isset($settings['map_oms']['map_oms_control']) ? $settings['map_oms']['map_oms_control'] : $default_settings['map_oms']['map_oms_control'],
      '#return_value' => 1,
    ];
    $elements['map_oms']['map_oms_options'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('Markers Spiderfy Options'),
      '#description' => $this->t('An object literal of Spiderfy options, that comply with the Overlapping Marker Spiderfier Library (see link above).<br>The syntax should respect the javascript object notation (json) format.<br>Always use double quotes (") both for the indexes and the string values.<br><b>Note: </b>This first three default options are the library ones suggested to save memory and CPU (in the simplest/standard implementation).'),
      '#default_value' => isset($settings['map_oms']['map_oms_options']) ? $settings['map_oms']['map_oms_options'] : $default_settings['map_oms']['map_oms_options'],
      '#placeholder' => '{"markersWontMove": "true", "markersWontHide": "true", "basicFormatEvents": "true", "nearbyDistance": 3}',
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    if (isset($field_definition)) {
      $elements['map_oms']['map_oms_options']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $elements['map_oms']['map_oms_options']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
      ];
    }

    $elements['custom_style_map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom Styled Map'),
    ];
    $elements['custom_style_map']['custom_style_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create a @custom_google_map_style_link.', [
        '@custom_google_map_style_link' => $this->link->generate($this->t('Custom Google Map Style'), Url::fromUri('https://developers.google.com/maps/documentation/javascript/examples/maptype-styled-simple', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#description' => $this->t('This option allows to create a new map type, which the user can select from the map type control. The map type includes custom styles.'),
      '#default_value' => $settings['custom_style_map']['custom_style_control'],
      '#return_value' => 1,
    ];
    $elements['custom_style_map']['custom_style_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Map Style Name'),
      '#description' => $this->t('Input the Name of the Custom Map Style you want to create.'),
      '#default_value' => $settings['custom_style_map']['custom_style_name'],
      '#placeholder' => $this->t('My Custom Map Style'),
      '#element_validate' => [[get_class($this), 'customMapStyleValidate']],
    ];
    $elements['custom_style_map']['custom_style_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Custom Map Style Options'),
      '#description' => $this->t('An object literal of map style options, that comply with the Google Maps JavaScript API.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br>(As a useful reference consider using @snappy_maps).', [
        '@snappy_maps' => $this->link->generate($this->t('Snappy Maps'), Url::fromUri('https://snazzymaps.com', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#default_value' => $settings['custom_style_map']['custom_style_options'],
      '#placeholder' => $this->customMapStylePlaceholder,
      '#element_validate' => [
        [get_class($this), 'jsonValidate'],
        [get_class($this), 'customMapStyleValidate'],
      ],
    ];
    $elements['custom_style_map']['custom_style_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force the Custom Map Style as Default'),
      '#description' => $this->t('The Custom Map Style will be the Default starting one.'),
      '#default_value' => $settings['custom_style_map']['custom_style_default'],
      '#return_value' => 1,
    ];

    if (isset($field_definition)) {
      $custom_style_map_control_selector = ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][custom_style_map][custom_style_control]"]';
    }
    else {
      $custom_style_map_control_selector = ':input[name="style_options[custom_style_map][custom_style_control]"]';
    }
    $elements['custom_style_map']['custom_style_name']['#states'] = [
      'visible' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
      'required' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
    ];
    $elements['custom_style_map']['custom_style_options']['#states'] = [
      'visible' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
    ];
    $elements['custom_style_map']['custom_style_default']['#states'] = [
      'visible' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
    ];

    $elements['map_markercluster'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Marker Clustering'),
    ];
    $elements['map_markercluster']['markup'] = [
      '#markup' => $this->t('Enable the functionality of the @markeclusterer_api_link.', [
        '@markeclusterer_api_link' => $this->link->generate($this->t('Marker Clusterer Google Maps JavaScript Library'), Url::fromUri('https://github.com/googlemaps/js-marker-clusterer', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];
    $elements['map_markercluster']['markercluster_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Marker Clustering'),
      '#default_value' => $settings['map_markercluster']['markercluster_control'],
      '#return_value' => 1,
    ];
    $elements['map_markercluster']['markercluster_additional_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Marker Cluster Additional Options'),
      '#description' => $this->t('An object literal of additional marker cluster options, that comply with the Marker Clusterer Google Maps JavaScript Library.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
      '#default_value' => $settings['map_markercluster']['markercluster_additional_options'],
      '#placeholder' => '{"maxZoom": 12, "gridSize": 25, "imagePath": "https://developers.google.com/maps/documentation/javascript/examples/full/images/beachflag.png"}',
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    $elements['map_markercluster']['markercluster_warning'] = [
      '#type' => 'container',
      'warning' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('WARNING:') . " ",
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ],
      'warning_text' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Markers Spiderfy is Active ! | If not, a "maxZoom" property should be set in the Marker Cluster Additional Options to be able to output the Spederfy effect.'),
      ],
    ];

    if (isset($field_definition)) {
      $elements['map_markercluster']['markercluster_additional_options']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_markercluster][markercluster_control]"]' => ['checked' => TRUE],
        ],
      ];
      $elements['map_markercluster']['markercluster_warning']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][map_markercluster][markercluster_control]"]' => ['checked' => FALSE],
        ],
      ];
    }
    else {
      $elements['map_markercluster']['markercluster_additional_options']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_markercluster][markercluster_control]"]' => ['checked' => TRUE],
        ],
      ];
      $elements['map_markercluster']['markercluster_warning']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          ':input[name="style_options[map_markercluster][markercluster_control]"]' => ['checked' => FALSE],
        ],
      ];
    }

    return $elements;

  }

  /**
   * Form element validation handler for a Map Zoom level.
   *
   * {@inheritdoc}
   */
  public static function zoomLevelValidate($element, FormStateInterface &$form_state) {
    // Get to the actual values in a form tree.
    $parents = $element['#parents'];
    $values = $form_state->getValues();
    for ($i = 0; $i < count($parents) - 1; $i++) {
      $values = $values[$parents[$i]];
    }
    // Check the initial map zoom level.
    $zoom = $element['#value'];
    $min_zoom = $values['min'];
    $max_zoom = $values['max'];
    if ($zoom < $min_zoom || $zoom > $max_zoom) {
      $form_state->setError($element, t('The @zoom_field should be between the Minimum and the Maximum Zoom levels.', ['@zoom_field' => $element['#title']]));
    }
  }

  /**
   * Form element validation handler for the Map Max Zoom level.
   *
   * {@inheritdoc}
   */
  public static function maxZoomLevelValidate($element, FormStateInterface &$form_state) {
    // Get to the actual values in a form tree.
    $parents = $element['#parents'];
    $values = $form_state->getValues();
    for ($i = 0; $i < count($parents) - 1; $i++) {
      $values = $values[$parents[$i]];
    }
    // Check the max zoom level.
    $min_zoom = $values['min'];
    $max_zoom = $element['#value'];
    if ($max_zoom && $max_zoom <= $min_zoom) {
      $form_state->setError($element, t('The Max Zoom level should be above the Minimum Zoom level.'));
    }
  }

  /**
   * Form element validation handler for a Custom Map Style Name Required.
   *
   * {@inheritdoc}
   */
  public static function customMapStyleValidate($element, FormStateInterface &$form_state) {
    // Get to the actual values in a form tree.
    $parents = $element['#parents'];
    $values = $form_state->getValues();
    for ($i = 0; $i < count($parents) - 1; $i++) {
      $values = $values[$parents[$i]];
    }
    if ($values['custom_style_control'] && empty($element['#value'])) {
      $form_state->setError($element, t('The @field cannot be empty.', ['@field' => $element['#title']]));
    }
  }

  /**
   * Form element json format validation handler.
   *
   * {@inheritdoc}
   */
  public static function jsonValidate($element, FormStateInterface &$form_state) {
    $element_values_array = JSON::decode($element['#value']);
    // Check the jsonValue.
    if (!empty($element['#value']) && $element_values_array == NULL) {
      $form_state->setError($element, t('The @field field is not valid Json Format.', ['@field' => $element['#title']]));
    }
    elseif (!empty($element['#value'])) {
      $form_state->setValueForElement($element, JSON::encode($element_values_array));
    }
  }

  /**
   * Form element url format validation handler.
   *
   * {@inheritdoc}
   */
  public static function urlValidate($element, FormStateInterface &$form_state) {
    $path = $element['#value'];
    // Check the jsonValue.
    if (UrlHelper::isExternal($path) && !UrlHelper::isValid($path, TRUE)) {
      $form_state->setError($element, t('The @field field is not valid Url Format.', ['@field' => $element['#title']]));
    }
    elseif (!UrlHelper::isExternal($path)) {
      $path = Url::fromUri('base:' . $path, ['absolute' => TRUE])->toString();
      if (!UrlHelper::isValid($path)) {
        $form_state->setError($element, t('The @field field is not valid internal Drupal path.', ['@field' => $element['#title']]));
      }
    }
  }

  /**
   * Pre Process the MapSettings.
   *
   * Performs some preprocess on the maps settings before sending to js.
   *
   * @param array $map_settings
   *   The map settings.
   */
  protected function preProcessMapSettings(array &$map_settings) {
    // Set the gmap_api_key as map settings.
    $map_settings['gmap_api_key'] = $this->getGmapApiKey();

    // Transform into simple array values the map_type_control_options_type_ids.
    $map_settings['map_controls']['map_type_control_options_type_ids'] = array_keys(array_filter($map_settings['map_controls']['map_type_control_options_type_ids'], function ($value) {
      return $value !== 0;
    }));

    // Generate Absolute icon_image_path, if it is not.
    $icon_image_path = $map_settings['map_marker_and_infowindow']['icon_image_path'];
    if (!empty($icon_image_path) && !UrlHelper::isExternal($map_settings['map_marker_and_infowindow']['icon_image_path'])) {
      $map_settings['map_marker_and_infowindow']['icon_image_path'] = Url::fromUri('base:' . $icon_image_path, ['absolute' => TRUE])
        ->toString();
    }
  }

  /**
   * Transform Geofield data into Geojson features.
   *
   * @param mixed $items
   *   The Geofield Data Values.
   * @param string $description
   *   The description value.
   * @param mixed $additional_data
   *   Additional data to be added to the feature properties, i.e.
   *   GeofieldGoogleMapViewStyle will add row fields (already rendered).
   *
   * @return array
   *   The data array for the current feature, including Geojson and additional
   *   data.
   */
  protected function getGeoJsonData($items, $description = NULL, $additional_data = NULL) {
    $data = [];
    foreach ($items as $delta => $item) {

      /* @var \Point $geometry */
      if (is_a($item, '\Drupal\geofield\Plugin\Field\FieldType\GeofieldItem') && isset($item->value)) {
        $geometry = $this->geoPhpWrapper->load($item->value);
      }
      elseif (preg_match('/^(POINT).*\(.*.*\)$/', $item)) {
        $geometry = $this->geoPhpWrapper->load($item);
      }
      if (isset($geometry)) {
        $datum = [
          "type" => "Feature",
          "geometry" => json_decode($geometry->out('json')),
        ];
        $datum['properties'] = [
          // If a multivalue field value with the same index exist, use this,
          // else use the first item as fallback.
          'description' => isset($description[$delta]) ? $description[$delta] : (isset($description[0]) ? $description[0] : NULL),
          'data' => $additional_data,
        ];
        $data[] = $datum;
      }
    }
    return $data;
  }

}
