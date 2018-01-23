<?php

namespace Drupal\geofield_map\Plugin\views\style;

use Drupal\geofield_map\GeofieldMapFieldTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\DefaultStyle;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "geofield_google_map",
 *   title = @Translation("Geofield Google Map"),
 *   help = @Translation("Displays a View as a Geofield Google Map."),
 *   display_types = {"normal"},
 *   theme = "geofield-google-map"
 * )
 */
class GeofieldGoogleMapViewStyle extends DefaultStyle implements ContainerFactoryPluginInterface {

  use GeofieldMapFieldTrait;

  /**
   * Empty Map Options.
   *
   * @var array
   */
  protected $emptyMapOptions = [
    '0' => 'View No Results Behaviour',
    '1' => 'Empty Map Centered at the Default Center',
  ];

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Entity type property.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The Entity Info service property.
   *
   * @var string
   */
  protected $entityInfo;

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The Entity Field manager service property.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Entity Display Repository service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplay;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a GeofieldGoogleMapView style instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display
   *   The entity display manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The The geoPhpWrapper.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityDisplayRepositoryInterface $entity_display,
    RendererInterface $renderer,
    LinkGeneratorInterface $link_generator,
    GeoPHPInterface $geophp_wrapper,
    AccountInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplay = $entity_display;
    $this->config = $config_factory;
    $this->renderer = $renderer;
    $this->link = $link_generator;
    $this->geoPhpWrapper = $geophp_wrapper;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('renderer'),
      $container->get('link_generator'),
      $container->get('geofield.geophp'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // For later use, set entity info related to the View's base table.
    $base_tables = array_keys($view->getBaseTables());
    $base_table = reset($base_tables);
    foreach ($this->entityManager->getDefinitions() as $key => $info) {
      if ($info->getDataTable() == $base_table) {
        $this->entityType = $key;
        $this->entityInfo = $info;
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    // Render map even if there is no data.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $default_settings = self::defineOptions();

    // Get a list of fields and a sublist of geo data fields in this view.
    $fields = [];
    $fields_geo_data = [];
    /* @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $fields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\EntityField')) {
        /* @var \Drupal\views\Plugin\views\field\EntityField $handler */
        $field_storage_definitions = $this->entityFieldManager
          ->getFieldStorageDefinitions($handler->getEntityType());
        $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];

        if ($field_storage_definition->getType() == 'geofield') {
          $fields_geo_data[$field_id] = $label;
        }
      }
    }

    // Check whether we have a geo data field we can work with.
    if (empty($fields_geo_data)) {
      $form['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Please add at least one Geofield to the View and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
        '#attached' => [
          'library' => [
            'geofield_map/geofield_map_general',
          ],
        ],
      ];
      return;
    }

    // Map data source.
    $form['data_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Source'),
      '#description' => $this->t('Which field contains geodata?'),
      '#options' => $fields_geo_data,
      '#default_value' => $this->options['data_source'],
      '#required' => TRUE,
    ];

    $desc_options = array_merge(['0' => $this->t('- Any - No Infowindow')], $fields);
    // Add an option to render the entire entity using a view mode.
    if ($this->entityType) {
      $desc_options += [
        '#rendered_entity' => $this->t('- Rendered @entity entity -', ['@entity' => $this->entityType]),
      ];
    }

    $this->options['infowindow_content_options'] = $desc_options;

    if ($this->entityType) {

      // Get the human readable labels for the entity view modes.
      $view_mode_options = [];
      foreach ($this->entityDisplay->getViewModes($this->entityType) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $form['view_mode'] = [
        '#fieldset' => 'map_marker_and_infowindow',
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode the entity will be displayed in the Infowindow.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($this->options['view_mode']) ? $this->options['view_mode'] : 'full',
        '#states' => [
          'visible' => [
            ':input[name="style_options[map_marker_and_infowindow][infowindow_field]"]' => [
              'value' => '#rendered_entity',
            ],
          ],
        ],
      ];
    }

    $form = $form + $this->generateGmapSettingsForm($form, $form_state, $this->options, $default_settings);

  }

  /**
   * Renders the View.
   */
  public function render() {

    $map_settings = $this->options;
    $element = [];

    // Performs some preprocess on the maps settings before sending to js.
    $this->preProcessMapSettings($map_settings);

    $js_settings = [
      'mapid' => Html::getUniqueId("geofield_map_view_" . $this->view->id() . '_' . $this->view->current_display),
      'map_settings' => $map_settings,
      'data' => [],
    ];

    $data = [];

    // Get the Geofield field.
    $geofield_name = $map_settings['data_source'];

    // If the Geofield field is null, output a warning
    // to the Geofield Map administrator.
    if (empty($geofield_name) && $this->currentUser->hasPermission('configure geofield_map')) {
      $element = [
        '#markup' => '<div class="geofield-map-warning">' . $this->t("The Geofield field hasn't not been correctly set for this View. <br>Add at least one Geofield to the View and set it as Data Source in the Geofield Google Map View Display Settings.") . "</div>",
        '#attached' => [
          'library' => ['geofield_map/geofield_map_general'],
        ],
      ];
    }

    // It the Geofield field is not null, and there are results or a not null
    // empty behaviour has been set, render the results.
    if (!empty($geofield_name) && (!empty($this->view->result) || $map_settings['map_empty']['empty_behaviour'] == '1')) {
      $this->renderFields($this->view->result);
      /* @var \Drupal\views\ResultRow  $result */
      foreach ($this->view->result as $id => $result) {

        $geofield_value = $this->getFieldValue($id, $geofield_name);

        // In case the result is not among the raw results, get it from the
        // rendered results.
        if (empty($geofield_value)) {
          $geofield_value = $this->rendered_fields[$id][$geofield_name];
        }

        // In case the result is not null.
        if (!empty($geofield_value)) {
          // If it is a single value field, transform into an array.
          $geofield_value = is_array($geofield_value) ? $geofield_value : [$geofield_value];

          $description_field = isset($map_settings['map_marker_and_infowindow']['infowindow_field']) ? $map_settings['map_marker_and_infowindow']['infowindow_field'] : NULL;
          $description = [];
          $entity = $result->_entity;

          // Render the entity with the selected view mode.
          if (isset($description_field) && $description_field === '#rendered_entity' && is_object($result)) {
            $build = $this->entityManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $map_settings['view_mode'], $entity->language()->getId());
            $description[] = $this->renderer->renderRoot($build);
          }
          // Normal rendering via fields.
          elseif (isset($description_field)) {
            $description_field_name = strtolower($map_settings['map_marker_and_infowindow']['infowindow_field']);
            if (isset($entity->$description_field_name)) {
              // Check if the entity has a $description_field_name field.
              foreach ($entity->$description_field_name->getValue() as $value) {
                if ($map_settings['map_marker_and_infowindow']['multivalue_split'] == FALSE) {
                  $description[] = $this->rendered_fields[$id][$description_field];
                  break;
                }
                $description[] = isset($value['value']) ? $value['value'] : '';
              }
            }
            // Else get the views field value.
            elseif (isset($this->rendered_fields[$id][$description_field])) {
              $description[] = $this->rendered_fields[$id][$description_field];
            }
          }

          // Add Views fields to the Json output as additional_data property.
          $view_data = [];
          foreach ($this->rendered_fields[$id] as $field_name => $rendered_field) {
            if (!empty($rendered_field) && !$this->view->field[$field_name]->options['exclude']) {
              /* @var \Drupal\Core\Render\Markup $rendered_field */
              $view_data[$field_name] = $rendered_field->__toString();
            }
          }
          $data = array_merge($data, $this->getGeoJsonData($geofield_value, $description, $view_data));
        }
      }

      $js_settings['data'] = [
        'type' => 'FeatureCollection',
        'features' => $data,
      ];

      $element = geofield_map_googlemap_render($js_settings);

    }
    return $element;
  }

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['data_source'] = ['default' => ''];
    $options['name_field'] = ['default' => ''];
    $options['description_field'] = ['default' => ''];
    $options['view_mode'] = ['default' => 'full'];

    $geofield_google_map_default_settings = [];
    foreach (self::getDefaultSettings() as $k => $setting) {
      $geofield_google_map_default_settings[$k] = ['default' => $setting];
    }

    return $options + $geofield_google_map_default_settings;
  }

}
