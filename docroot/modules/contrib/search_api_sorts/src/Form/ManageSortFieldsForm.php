<?php

namespace Drupal\search_api_sorts\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_sorts\ConfigIdEscapeTrait;
use Drupal\search_api_sorts\Entity\SearchApiSortsField;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing sort fields for a search api display.
 */
class ManageSortFieldsForm extends FormBase {
  use ConfigIdEscapeTrait;

  /**
   * The search api sorts field storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $searchApiSortsFieldStorage;

  /**
   * The index this search api display is attached to.
   *
   * @var string
   */
  protected $index;

  /**
   * The search api display used by the form.
   *
   * @var string
   */
  protected $display;

  /**
   * Constructs the DisplaySortsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->searchApiSortsFieldStorage = $entity_type_manager->getStorage('search_api_sorts_field');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_sorts_display_sorts_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, IndexInterface $search_api_index = NULL, $search_api_display = NULL) {

    $original_search_api_display = $this->getOriginalConfigId($search_api_display);
    $display_plugin_manager = \Drupal::service('plugin.manager.search_api.display');
    $this->display = $display_plugin_manager->createInstance($original_search_api_display);
    $this->index = $search_api_index;

    if ($disabled = empty($this->index->status())) {
      drupal_set_message($this->t('Since the index for this display is at the moment disabled, no sorts can be activated.'), 'warning');
    }

    $form['#title'] = $this->t('Manage sort fields for %label', ['%label' => $this->display->label()]);

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('Select the available sorts'),
      '#description' => $this->t('<p>Only index single-value strings or numbers can be used as sorts. See the Fields tab to change indexed fields.</p>'),
    ];

    $form['sorts'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Weight'),
        $this->t('Enabled'),
        $this->t('Default sort'),
        $this->t('Default order'),
        $this->t('Field'),
        $this->t('Type'),
        $this->t('Label'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'search-api-sort-order-weight',
        ],
      ],
      '#empty' => $this->t('There are currently no fields for which sorts can be displayed.'),
    ];

    $fields = $this->getSearchApiSortsFieldsValues();

    foreach ($fields as $key => $field) {
      $form['sorts'][$key]['#attributes']['class'][] = 'draggable';
      $form['sorts'][$key]['weight'] = [
        '#type' => 'weight',
        '#default_value' => $field['weight'],
        '#delta' => 100,
        '#attributes' => [
          'class' => ['search-api-sort-order-weight'],
        ],
      ];
      $form['sorts'][$key]['status'] = [
        '#type' => 'checkbox',
        '#default_value' => $field['status'],
        '#disabled' => $disabled,
      ];
      $form['sorts'][$key]['default_sort'] = [
        '#type' => 'radio',
        '#return_value' => $key,
        '#tree' => FALSE,
        '#default_value' => $field['default_sort'],
        '#states' => [
          'enabled' => [
            ':input[name="sorts[' . $key . '][status]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['sorts'][$key]['default_order'] = [
        '#type' => 'select',
        '#default_value' => $field['default_order'],
        '#options' => [
          'asc' => $this->t('Ascending'),
          'desc' => $this->t('Descending'),
        ],
        '#states' => [
          'visible' => [
            ':input[name="sorts[' . $key . '][status]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['sorts'][$key]['field'] = [
        '#markup' => Html::escape($field['field']),
      ];
      $form['sorts'][$key]['type'] = [
        '#markup' => $field['type'],
      ];
      $form['sorts'][$key]['label'] = [
        '#type' => 'textfield',
        '#maxlength' => max(strlen($field['label']), 80),
        '#size' => 30,
        '#default_value' => $field['label'],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
    ];

    return $form;
  }

  /**
   * Returns an array of all saved search api sorts fields.
   *
   * @return array
   *   An array of fields, filled with values from the index.
   */
  protected function getSearchApiSortsFieldsValues() {
    $fields = $this->buildSearchApiSortsFieldsDefaultValues();
    $this->fillSearchApiSortsFieldsValues($fields);

    return $fields;
  }

  /**
   * An array of sortable fields with default values.
   *
   * @return array
   *   An array of fields.
   */
  private function buildSearchApiSortsFieldsDefaultValues() {
    // Add our dummy relevance field.
    $fields = [
      'search_api_relevance' => [
        'status' => FALSE,
        'default_sort' => FALSE,
        'default_order' => 'desc',
        'field' => 'Relevance',
        'type' => 'decimal',
        'label' => $this->t('Relevance'),
        'weight' => 0,
      ],
    ];

    foreach ($this->index->getFields() as $field) {
      // Skip fulltext or multi-value, you cannot sort them.
      if ($field->getType() == 'text' || strpos($field->getType(), 'list<') !== FALSE) {
        continue;
      }

      $fields[$field->getFieldIdentifier()] = [
        'status' => FALSE,
        'default_sort' => FALSE,
        'default_order' => 'asc',
        'field' => $field->getLabel(),
        'type' => $field->getType(),
        'label' => $field->getLabel(),
        'weight' => 0,
      ];
    }

    return $fields;
  }

  /**
   * Fills the array build by buildDefaultFieldValues().
   *
   * @param array $fields
   *   An array of fields, filled with data from the index.
   */
  private function fillSearchApiSortsFieldsValues(&$fields) {
    $search_api_sorts_fields = $this->searchApiSortsFieldStorage->loadByProperties(['display_id' => $this->getEscapedConfigId($this->display->getPluginId())]);
    foreach ($search_api_sorts_fields as $search_api_sorts_field) {
      if (isset($fields[$search_api_sorts_field->getFieldIdentifier()])) {
        $fields[$search_api_sorts_field->getFieldIdentifier()]['status'] = $search_api_sorts_field->getStatus();
        $fields[$search_api_sorts_field->getFieldIdentifier()]['default_sort'] = $search_api_sorts_field->getDefaultSort();
        $fields[$search_api_sorts_field->getFieldIdentifier()]['default_order'] = $search_api_sorts_field->getDefaultOrder();
        $fields[$search_api_sorts_field->getFieldIdentifier()]['label'] = $search_api_sorts_field->getLabel();
        $fields[$search_api_sorts_field->getFieldIdentifier()]['weight'] = $search_api_sorts_field->getWeight();
      }
    }

    // Sort the fields by the weight element.
    uasort($fields, [
      'Drupal\Component\Utility\SortArray',
      'sortByWeightElement',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('sorts') as $key => $v) {
      if ($v['status']) {
        if (!$v['label']) {
          $form_state->setErrorByName("sorts][$key][label", $this->t("You can't set an empty label."));
        }
        elseif (strlen($v['label']) > 80) {
          $form_state->setErrorByName("sorts][$key][label", $this->t('Labels cannot be longer than 80 characters, but "@label" is @count characters long.',
            ['@label' => $v['label'], '@count' => strlen($v['label'])]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $search_api_sorts_fields = $this->searchApiSortsFieldStorage->loadByProperties(['display_id' => $this->getEscapedConfigId($this->display->getPluginId())]);
    foreach ($form_state->getValue('sorts') as $key => $v) {
      if (isset($search_api_sorts_fields[$this->getEscapedConfigId($this->display->getPluginId()) . '_' . $key])) {
        $search_api_sorts_field = $search_api_sorts_fields[$this->getEscapedConfigId($this->display->getPluginId()) . '_' . $key];
      }
      else {
        $search_api_sorts_field = SearchApiSortsField::create();
        $search_api_sorts_field->set('id', $this->getEscapedConfigId($this->display->getPluginId()) . '_' . $key);
        $search_api_sorts_field->set('field_identifier', $key);
        $search_api_sorts_field->set('display_id', $this->getEscapedConfigId($this->display->getPluginId()));
      }

      $search_api_sorts_field->set('status', $v['status']);
      $search_api_sorts_field->set('default_sort', $form_state->getValue('default_sort') == $key);
      $search_api_sorts_field->set('default_order', $v['default_order']);
      $search_api_sorts_field->set('label', $v['label']);
      $search_api_sorts_field->set('weight', $v['weight']);
      $search_api_sorts_field->save();
    }
    drupal_set_message($this->t('The changes were successfully saved.'));
  }

}
