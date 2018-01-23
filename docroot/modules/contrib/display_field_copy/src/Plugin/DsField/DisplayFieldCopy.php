<?php

namespace Drupal\display_field_copy\Plugin\DsField;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ds\Plugin\DsField\DsFieldBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a generic dynamic field that holds a copy of an exisitng core field.
 *
 * @DsField(
 *   id = "display_field_copy",
 *   deriver = "Drupal\display_field_copy\Plugin\Derivative\DisplayFieldCopy",
 * )
 */
class DisplayFieldCopy extends DsFieldBase implements ContainerFactoryPluginInterface {

  /**
   * Field Definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * Formatter.
   *
   * @var \Drupal\Core\Field\FormatterInterface.
   */
  protected $formatter;

  /**
   * Formatter Plugin Manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $formatterPluginManager;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a Display Suite field plugin.
   */
  public function __construct($configuration,
                              $plugin_id,
                              $plugin_definition,
                              PluginManagerInterface $formatter_plugin_manager,
                              EntityTypeManagerInterface $entity_type_manager,
                              EntityFieldManagerInterface $entity_field_manager) {
    $this->formatterPluginManager = $formatter_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.field.formatter'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $formatter = $this->getFormatter([
      'type' => $this->getFieldConfiguration()['formatter'],
    ]);

    $items = $this->entity()->get($this->getRenderKey());

    return $formatter->viewElements($items, $this->entity()->language()->getId());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return $this->getFormatter()->defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm($form, FormStateInterface $form_state) {
    $formatter_id = $form_state->getUserInput()['fields'][$this->getName()]['plugin']['type'];

    $formatter = $this->getFormatter([
      'type' => $formatter_id,
    ]);

    return [
      'formatter' => $formatter->settingsForm($form, $form_state),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formatters() {
    return $this->formatterPluginManager->getOptions($this->getFieldDefinition()->getType());
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderKey() {
    $field_id = $this->getFieldConfiguration()['properties']['field_id'];
    $pieces = explode('.', $field_id);
    return end($pieces);
  }

  /**
   * Return the field definition.
   */
  protected function getFieldDefinition() {
    if (!$this->fieldDefinition) {
      $field_id = $this->pluginDefinition['properties']['field_id'];
      $pieces = explode('.', $field_id);
      $entity_type_id = $pieces[0];

      if (count($pieces) == 3) {
        $storage = $this->entityTypeManager->getStorage('field_config');
        $this->fieldDefinition = $storage->load($field_id);
      }
      else {
        $id = $pieces[1];
        $this->fieldDefinition = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id)[$id];
      }
    }

    return $this->fieldDefinition;
  }

  /**
   * Get the formatter configuration.
   */
  protected function getFormatterConfiguration() {
    $config = $this->getConfiguration();

    return isset($config['formatter']) ? $config['formatter'] : [];
  }

  /**
   * Return the field formatter.
   */
  protected function getFormatter(array $configuration = []) {
    if (!isset($configuration['settings'])) {
      $configuration['settings'] = $this->getFormatterConfiguration();
    }

    return $this->formatterPluginManager->getInstance([
      'field_definition' => $this->getFieldDefinition(),
      'view_mode' => $this->viewMode(),
      'prepare' => TRUE,
      'configuration' => $configuration,
    ]);
  }

}
