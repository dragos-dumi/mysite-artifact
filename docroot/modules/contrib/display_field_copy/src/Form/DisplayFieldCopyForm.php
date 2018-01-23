<?php

namespace Drupal\display_field_copy\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ds\Form\FieldFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure copy fields.
 */
class DisplayFieldCopyForm extends FieldFormBase {

  /**
   * The type of the dynamic ds field.
   */
  const TYPE = 'display_field_copy';

  /**
   * Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity Type Bundle Info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              CacheTagsInvalidatorInterface $cache_invalidator,
                              ModuleHandlerInterface $module_handler,
                              EntityFieldManagerInterface $entity_field_manager,
                              EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($config_factory, $entity_type_manager, $cache_invalidator, $module_handler);
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('cache_tags.invalidator'),
      $container->get('module_handler'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ds_field_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $field_key = '') {
    $form = parent::buildForm($form, $form_state, $field_key);

    $form['entities']['#access'] = FALSE;
    $form['ui_limit']['#access'] = FALSE;

    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_id => $data) {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);

        foreach ($fields as $field_id => $field) {
          if ($field instanceof BaseFieldDefinition) {
            $options[$entity_type_id . '.' . $field_id] = $entity_type->getLabel() . ' - ' . $field->getLabel();
          }
          elseif ($field instanceof FieldConfigInterface) {
            $options[$field->id()] = $entity_type->getLabel() . ' (' . $data['label'] . ') - ' . $field->label();
          }
        }
      }
    }

    $field = $this->field;

    $form['ds_field_identity']['field_id'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#title' => t('Fields'),
      '#required' => TRUE,
      '#default_value' => isset($field['properties']['field_id']) ? $field['properties']['field_id'] : '',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field_id = $form_state->getValue('field_id');

    $pieces = explode('.', $field_id);
    $entity_type_id = $pieces[0];

    $entities = $form_state->getValue('entities');
    foreach ($entities as $key => $value) {
      $entities[$key] = 0;
    }
    $entities[$entity_type_id] = $entity_type_id;
    $form_state->setValue('entities', $entities);

    if (count($pieces) == 3) {
      $bundle_id = $pieces[1];
      $form_state->setValue('ui_limit', $bundle_id . '|*');
    }

    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties(FormStateInterface $form_state) {
    return array(
      'field_id' => $form_state->getValue('field_id'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return self::TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeLabel() {
    return 'Copy field';
  }

}
