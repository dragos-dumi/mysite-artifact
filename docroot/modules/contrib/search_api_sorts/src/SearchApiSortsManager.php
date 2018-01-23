<?php

namespace Drupal\search_api_sorts;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Display\DisplayInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Manages search api sorts.
 */
class SearchApiSortsManager implements SearchApiSortsManagerInterface {
  use ConfigIdEscapeTrait;

  /**
   * Current Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * SearchApiSortsManager constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack, containing the current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveSort(DisplayInterface $display) {
    $order = (strtolower($this->currentRequest->get('order')) === 'desc') ? 'desc' : 'asc';
    $active_sort = new SortsField($this->currentRequest->get('sort'), $order);

    // Allow altering the active sort (if there is an active sort).
    if ($active_sort->getFieldName()) {
      $this->moduleHandler->alter('search_api_sorts_active_sort', $active_sort, $display);
    }
    return $active_sort;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledSorts(DisplayInterface $display) {
    return $this->entityTypeManager
      ->getStorage('search_api_sorts_field')
      ->loadByProperties(['status' => TRUE, 'display_id' => $this->getEscapedConfigId($display->getPluginId())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSort(DisplayInterface $display) {

    // By default use relevance, which will be overridden when defaults are set.
    $default_sort = new SortsField('search_api_relevance', 'desc');

    foreach ($this->getEnabledSorts($display) as $enabled_sort) {
      if ($enabled_sort->getDefaultSort()) {
        $default_sort = new SortsField($enabled_sort->getFieldIdentifier(), $enabled_sort->getDefaultOrder());
      }
    }

    // Allow altering the default sort.
    \Drupal::moduleHandler()->alter('search_api_sorts_default_sort', $default_sort, $display);

    return $default_sort;
  }

}
